<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\StoreApp;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;

/**
 * Her araç veriye yalnızca bu trait üzerinden erişir: token sahibinin workspace'leri dışına çıkılmaz.
 */
trait ScopesToUser
{
    /**
     * @return Builder<StoreApp>
     */
    protected function apps(Request $request): Builder
    {
        return StoreApp::query()
            ->visibleTo($request->user())
            ->when($request->get('store_app_ids'), fn (Builder $query, array $ids) => $query->whereKey($ids))
            ->when($request->get('product_id'), fn (Builder $query, int $id) => $query->where('product_id', $id))
            ->when($request->get('store'), fn (Builder $query, string $store) => $query->where('store', $store))
            ->when($request->get('store_account_id'), fn (Builder $query, int $id) => $query->where('store_account_id', $id));
    }

    /**
     * @return array<string, mixed>
     */
    protected function appFilterRules(): array
    {
        return [
            'store_app_ids' => 'sometimes|array',
            'store_app_ids.*' => 'integer',
            'product_id' => 'sometimes|integer',
            'store' => 'sometimes|in:google_play,app_store,app_gallery',
            'store_account_id' => 'sometimes|integer',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function appFilterSchema(JsonSchema $schema): array
    {
        return [
            'store_app_ids' => $schema->array()->items($schema->integer())
                ->description('Belirli mağaza uygulamaları (list-apps çıktısındaki store_app id\'leri).'),
            'product_id' => $schema->integer()
                ->description('Ürün ID\'si; ürünün tüm mağazalardaki uygulamalarını kapsar.'),
            'store' => $schema->string()->enum(['google_play', 'app_store', 'app_gallery'])
                ->description('Tek mağazaya sınırla.'),
            'store_account_id' => $schema->integer()
                ->description('Tek geliştirici hesabına sınırla.'),
        ];
    }
}
