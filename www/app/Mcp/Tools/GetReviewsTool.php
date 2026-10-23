<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ScopesToUser;
use App\Models\Review;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Kullanıcı yorumlarını getirir; puan, tarih, dil, ülke, uygulama versiyonu ve metin araması ile filtrelenebilir. En yeni önce, en fazla 200 kayıt. Duygu/tema analizi için kullan.')]
class GetReviewsTool extends Tool
{
    use ScopesToUser;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            ...$this->appFilterRules(),
            'from' => 'sometimes|date',
            'to' => 'sometimes|date',
            'min_rating' => 'sometimes|integer|between:1,5',
            'max_rating' => 'sometimes|integer|between:1,5',
            'language' => 'sometimes|string',
            'country' => 'sometimes|string|size:2',
            'app_version' => 'sometimes|string',
            'search' => 'sometimes|string',
            'unreplied' => 'sometimes|boolean',
            'limit' => 'sometimes|integer|min:1|max:200',
        ]);

        $reviews = Review::query()
            ->with('storeApp:id,name,store')
            ->whereIn('store_app_id', $this->apps($request)->select('id'))
            ->when($validated['from'] ?? null, fn ($query, $from) => $query->where('reviewed_at', '>=', $from))
            ->when($validated['to'] ?? null, fn ($query, $to) => $query->where('reviewed_at', '<', now()->parse($to)->addDay()))
            ->when($validated['min_rating'] ?? null, fn ($query, $rating) => $query->where('rating', '>=', $rating))
            ->when($validated['max_rating'] ?? null, fn ($query, $rating) => $query->where('rating', '<=', $rating))
            ->when($validated['language'] ?? null, fn ($query, $language) => $query->where('language', 'like', $language.'%'))
            ->when($validated['country'] ?? null, fn ($query, $country) => $query->where('country', strtoupper($country)))
            ->when($validated['app_version'] ?? null, fn ($query, $version) => $query->where('app_version', $version))
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('body', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")))
            ->when($validated['unreplied'] ?? false, fn ($query) => $query->whereNull('replied_at'))
            ->latest('reviewed_at')
            ->limit($validated['limit'] ?? 50)
            ->get()
            ->map(fn (Review $review) => [
                'app' => $review->storeApp->name,
                'store' => $review->storeApp->store->value,
                'rating' => $review->rating,
                'title' => $review->title,
                'body' => $review->body,
                'language' => $review->language,
                'country' => $review->country,
                'app_version' => $review->app_version,
                'reviewed_at' => $review->reviewed_at->toIso8601String(),
                'replied' => $review->replied_at !== null,
            ])
            ->all();

        return Response::structured(['count' => count($reviews), 'reviews' => $reviews]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->appFilterSchema($schema),
            'from' => $schema->string()->description('YYYY-MM-DD'),
            'to' => $schema->string()->description('YYYY-MM-DD, dahil'),
            'min_rating' => $schema->integer()->description('1-5'),
            'max_rating' => $schema->integer()->description('1-5'),
            'language' => $schema->string()->description('Dil kodu öneki (ör. "tr").'),
            'country' => $schema->string()->description('ISO alpha-2 ülke kodu.'),
            'app_version' => $schema->string(),
            'search' => $schema->string()->description('Başlık/metin içinde arama.'),
            'unreplied' => $schema->boolean()->description('Sadece yanıtlanmamış yorumlar.'),
            'limit' => $schema->integer()->description('Varsayılan 50, en fazla 200.'),
        ];
    }
}
