<?php

namespace App\Jobs;

use App\Jobs\Concerns\RecordsSyncRun;
use App\Models\StoreAccount;
use App\Models\StoreApp;
use App\Stores\StoreRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Hesaptaki uygulamaları keşfeder, sonra her aktif uygulama için metrik ve yorum job'larını kuyruğa atar.
 */
class SyncStoreAccount implements ShouldQueue
{
    use Queueable, RecordsSyncRun;

    public function __construct(public StoreAccount $account) {}

    public function handle(StoreRegistry $registry): void
    {
        $collector = $registry->for($this->account->store);

        $this->recordSyncRun($this->account, null, function () use ($collector) {
            $count = 0;

            foreach ($collector->discoverApps($this->account) as $descriptor) {
                // Uygulama aynı mağazanın başka bir hesabındaysa (ör. Play'de tek Google bağlantısı
                // geliştirici hesaplarına bölündüğünde) kopyalanmaz, metrik ve ürün bağıyla birlikte taşınır.
                StoreApp::query()
                    ->where('workspace_id', $this->account->workspace_id)
                    ->where('store', $this->account->store)
                    ->where('external_id', $descriptor->externalId)
                    ->where('store_account_id', '!=', $this->account->id)
                    ->update(['store_account_id' => $this->account->id]);

                // Ad yalnızca ilk keşifte yazılır; panelde değiştirilen ad korunur
                // (Google Play adı vermiyor, paket adıyla başlar).
                $this->account->storeApps()->firstOrCreate(
                    ['external_id' => $descriptor->externalId],
                    [
                        'workspace_id' => $this->account->workspace_id,
                        'store' => $this->account->store,
                        'name' => $descriptor->name,
                        'icon_url' => $descriptor->iconUrl,
                    ],
                );
                $count++;
            }

            return $count;
        });

        foreach ($this->account->storeApps()->where('is_active', true)->get() as $app) {
            SyncAppMetrics::dispatch($app);
            SyncAppReviews::dispatch($app);
        }
    }
}
