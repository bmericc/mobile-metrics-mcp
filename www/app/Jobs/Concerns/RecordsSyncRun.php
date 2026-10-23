<?php

namespace App\Jobs\Concerns;

use App\Enums\SyncStatus;
use App\Models\StoreAccount;
use App\Models\StoreApp;
use App\Models\SyncRun;
use Closure;
use Throwable;

trait RecordsSyncRun
{
    /**
     * Callback işlenen satır sayısını döndürür.
     *
     * @param  Closure(): int  $callback
     */
    protected function recordSyncRun(StoreAccount $account, ?StoreApp $app, Closure $callback): void
    {
        $run = SyncRun::create([
            'store_account_id' => $account->id,
            'store_app_id' => $app?->id,
            'job' => class_basename(static::class),
            'status' => SyncStatus::Running,
            'started_at' => now(),
        ]);

        try {
            $rows = $callback();

            $run->update(['status' => SyncStatus::Succeeded, 'rows' => $rows, 'finished_at' => now()]);
        } catch (Throwable $e) {
            $run->update(['status' => SyncStatus::Failed, 'error' => $e->getMessage(), 'finished_at' => now()]);

            throw $e;
        }
    }
}
