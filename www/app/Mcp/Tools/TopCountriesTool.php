<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ScopesToUser;
use App\Models\DailyMetric;
use App\Stores\Metric;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Bir sayaç metriğinde (installs, revenue vb.) seçilen dönemde en yüksek değere sahip ülkeleri döndürür.')]
class TopCountriesTool extends Tool
{
    use ScopesToUser;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            ...$this->appFilterRules(),
            'metric' => 'required|in:'.implode(',', Metric::all()),
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        $countries = DailyMetric::query()
            ->whereIn('store_app_id', $this->apps($request)->select('id'))
            ->where('metric', $validated['metric'])
            ->where('country', '!=', DailyMetric::ALL_COUNTRIES)
            ->whereBetween('date', [$validated['from'], $validated['to']])
            ->selectRaw('country, SUM(value) as value')
            ->groupBy('country')
            ->orderByDesc('value')
            ->limit($validated['limit'] ?? 10)
            ->get()
            ->map(fn ($row) => ['country' => $row->country, 'value' => round((float) $row->value, 4)])
            ->all();

        return Response::structured(['metric' => $validated['metric'], 'countries' => $countries]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->appFilterSchema($schema),
            'metric' => $schema->string()->enum(Metric::all())->required(),
            'from' => $schema->string()->required()->description('YYYY-MM-DD'),
            'to' => $schema->string()->required()->description('YYYY-MM-DD'),
            'limit' => $schema->integer()->description('Varsayılan 10, en fazla 100.'),
        ];
    }
}
