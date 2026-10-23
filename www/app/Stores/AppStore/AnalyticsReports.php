<?php

namespace App\Stores\AppStore;

use App\Models\RawReport;
use App\Models\StoreApp;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Analytics Reports API akışı:
 *   app → analyticsReportRequests (ONGOING) → reports → instances (DAILY) → segments (gz TSV).
 *
 * ONGOING istek ilk oluşturulduğunda Apple'ın raporları üretmesi 1-2 gün sürer.
 * API anahtarının Admin rolünde olması gerekir; aksi halde istek oluşturma 403 döner.
 */
class AnalyticsReports
{
    public function __construct(private readonly AppStoreClient $client) {}

    /**
     * Aktif ONGOING isteğin id'sini döner. Yoksa oluşturur ve null döner (henüz veri yok).
     */
    public function ongoingRequestId(StoreApp $app): ?string
    {
        foreach ($this->client->paginate("/v1/apps/{$app->external_id}/analyticsReportRequests", [
            'filter[accessType]' => 'ONGOING',
        ]) as $page) {
            foreach ($page['data'] as $request) {
                // Apple uzun süre indirilmeyen istekleri durdurur; durdurulmuş istek yeni rapor üretmez.
                if (! ($request['attributes']['stoppedDueToInactivity'] ?? false)) {
                    return $request['id'];
                }
            }
        }

        try {
            $this->client->post('/v1/analyticsReportRequests', [
                'data' => [
                    'type' => 'analyticsReportRequests',
                    'attributes' => ['accessType' => 'ONGOING'],
                    'relationships' => ['app' => ['data' => ['type' => 'apps', 'id' => $app->external_id]]],
                ],
            ]);
        } catch (RequestException $e) {
            if ($e->response->status() === 403) {
                throw new AnalyticsUnavailable(
                    'App Store analitik raporu bu uygulama için açık değil ve açmak Admin anahtarı istiyor. '
                    .'Admin vermeden indirme ve güncelleme sayıları için hesaba Vendor number girin (Satış ve Eğilimler raporu).',
                    previous: $e,
                );
            }

            throw $e;
        }

        return null;
    }

    /**
     * @param  array<string>  $names
     * @return array<string, string> rapor adı => rapor id
     */
    public function reportIds(string $requestId, array $names): array
    {
        $ids = [];

        foreach ($this->client->paginate("/v1/analyticsReportRequests/{$requestId}/reports", ['limit' => 200]) as $page) {
            foreach ($page['data'] as $report) {
                $name = $report['attributes']['name'] ?? null;

                if (in_array($name, $names, true)) {
                    $ids[$name] = $report['id'];
                }
            }
        }

        return $ids;
    }

    /**
     * processingDate >= $since olan günlük instance'ların segmentlerini indirir (yerelde varsa tekrar indirmez).
     *
     * @return iterable<RawReport>
     */
    public function dailySegments(StoreApp $app, string $reportName, string $reportId, CarbonImmutable $since): iterable
    {
        foreach ($this->client->paginate("/v1/analyticsReports/{$reportId}/instances", [
            'filter[granularity]' => 'DAILY',
            'limit' => 200,
        ]) as $page) {
            foreach ($page['data'] as $instance) {
                $processingDate = CarbonImmutable::parse($instance['attributes']['processingDate']);

                if ($processingDate->lt($since)) {
                    continue;
                }

                foreach ($this->client->paginate("/v1/analyticsReportInstances/{$instance['id']}/segments") as $segments) {
                    foreach ($segments['data'] as $segment) {
                        yield $this->store($app, $reportName, $processingDate, $segment);
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $segment
     */
    private function store(StoreApp $app, string $reportName, CarbonImmutable $processingDate, array $segment): RawReport
    {
        $path = sprintf(
            'raw-reports/app_store/%d/%d/%s/%s_%s.tsv.gz',
            $app->store_account_id,
            $app->id,
            Str::slug($reportName),
            $processingDate->toDateString(),
            $segment['id'],
        );

        $existing = RawReport::where('store_account_id', $app->store_account_id)->where('path', $path)->first();

        if ($existing && Storage::disk('local')->exists($path)) {
            return $existing;
        }

        $content = $this->client->download($segment['attributes']['url']);
        Storage::disk('local')->put($path, $content);

        return RawReport::updateOrCreate(
            ['store_account_id' => $app->store_account_id, 'path' => $path],
            [
                'checksum' => hash('sha256', $content),
                'store_app_id' => $app->id,
                'source' => 'app_store:'.$reportName,
                'period_start' => $processingDate,
                'period_end' => $processingDate,
                'processed_at' => null,
                'error' => null,
            ],
        );
    }
}
