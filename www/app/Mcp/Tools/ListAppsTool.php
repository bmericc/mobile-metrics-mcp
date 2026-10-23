<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ScopesToUser;
use App\Models\StoreApp;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Kullanıcının erişebildiği uygulamaları listeler: ürün, mağaza, geliştirici hesabı ve mağaza ID\'si ile. Diğer araçlarda kullanılacak store_app ve product id\'lerini bulmak için önce bunu çağır.')]
class ListAppsTool extends Tool
{
    use ScopesToUser;

    public function handle(Request $request): Response|ResponseFactory
    {
        $request->validate($this->appFilterRules());

        $apps = $this->apps($request)
            ->with(['product', 'storeAccount'])
            ->orderBy('name')
            ->get()
            ->map(fn (StoreApp $app) => [
                'store_app_id' => $app->id,
                'name' => $app->name,
                'store' => $app->store->value,
                'external_id' => $app->external_id,
                'product' => $app->product ? ['id' => $app->product->id, 'name' => $app->product->name] : null,
                'store_account' => ['id' => $app->store_account_id, 'label' => $app->storeAccount->label],
                'is_active' => $app->is_active,
            ])
            ->all();

        return Response::structured(['count' => count($apps), 'apps' => $apps]);
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->appFilterSchema($schema);
    }
}
