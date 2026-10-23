<?php

namespace App\Jobs;

use App\Jobs\Concerns\RecordsSyncRun;
use App\Models\StoreAccount;
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
                $this->account->storeApps()->updateOrCreate(
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
