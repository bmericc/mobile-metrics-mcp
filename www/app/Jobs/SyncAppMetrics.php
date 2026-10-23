<?php

namespace App\Jobs;

use App\Jobs\Concerns\RecordsSyncRun;
use App\Models\DailyMetric;
use App\Models\StoreApp;
use App\Stores\StoreRegistry;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncAppMetrics implements ShouldQueue
{
    use Queueable, RecordsSyncRun;

    /**
     * Mağazalar geç ve düzeltmeli veri yayınladığı için son günler her çalışmada yeniden çekilir.
     */
    public function __construct(public StoreApp $app, public int $days = 7) {}

    public function handle(StoreRegistry $registry): void
    {
        $collector = $registry->for($this->app->store);
        $period = CarbonPeriod::create(today()->subDays($this->days), today()->subDay());

        $this->recordSyncRun($this->app->storeAccount, $this->app, function () use ($collector, $period) {
            $rows = [];

            foreach ($collector->fetchMetrics($this->app, $period) as $row) {
                $rows[] = [
                    'store_app_id' => $this->app->id,
                    'date' => $row->date->toDateString(),
                    'country' => $row->country,
                    'metric' => $row->metric,
                    'value' => $row->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach (array_chunk($rows, 1000) as $chunk) {
                DailyMetric::upsert($chunk, ['store_app_id', 'date', 'country', 'metric'], ['value', 'updated_at']);
            }

            return count($rows);
        });
    }
}
