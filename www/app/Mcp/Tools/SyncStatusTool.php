<?php

namespace App\Mcp\Tools;

use App\Models\StoreAccount;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Geliştirici hesaplarının veri güncelliğini gösterir: son başarılı senkronizasyon, son hata. Analizden önce verinin ne kadar güncel olduğunu kontrol etmek için kullan.')]
class SyncStatusTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $accounts = StoreAccount::query()
            ->visibleTo($request->user())
            ->get()
            ->map(function (StoreAccount $account) {
                $lastSuccess = $account->syncRuns()->where('status', 'succeeded')->latest('finished_at')->first();
                $lastFailure = $account->syncRuns()->where('status', 'failed')->latest('finished_at')->first();

                return [
                    'store_account_id' => $account->id,
                    'label' => $account->label,
                    'store' => $account->store->value,
                    'status' => $account->status,
                    'last_success_at' => $lastSuccess?->finished_at?->toIso8601String(),
                    'last_failure' => $lastFailure && (! $lastSuccess || $lastFailure->finished_at > $lastSuccess->finished_at)
                        ? ['at' => $lastFailure->finished_at?->toIso8601String(), 'job' => $lastFailure->job, 'error' => $lastFailure->error]
                        : null,
                    'latest_metric_date' => $account->storeApps()
                        ->join('daily_metrics', 'daily_metrics.store_app_id', '=', 'store_apps.id')
                        ->max('daily_metrics.date'),
                ];
            })
            ->all();

        return Response::structured(['accounts' => $accounts]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
