<?php

namespace App\Stores\AppStore;

use App\Models\DailyMetric;
use App\Models\RawReport;
use App\Models\StoreAccount;
use App\Models\StoreApp;
use App\Stores\Contracts\StoreCollector;
use App\Stores\Data\AppDescriptor;
use App\Stores\Data\MetricRow;
use App\Stores\Data\ReviewData;
use App\Stores\Territory;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * App Store Connect API (https://api.appstoreconnect.apple.com/v1):
 *  - Uygulamalar: GET /apps
 *  - Metrikler: Analytics Reports API (bkz. AnalyticsReports, AnalyticsReportMapper)
 *  - Yorumlar: GET /apps/{id}/customerReviews
 *
 * Kimlik: credentials.issuer_id, credentials.key_id, credentials.private_key (.p8 içeriği).
 */
class AppStoreCollector implements StoreCollector
{
    public function __construct(
        private readonly JwtSigner $signer,
        private readonly AnalyticsReportMapper $mapper,
    ) {}

    public function verify(StoreAccount $account): void
    {
        try {
            $this->client($account)->get('/v1/apps', ['limit' => 1]);
        } catch (RequestException $e) {
            throw new RuntimeException(match ($e->response->status()) {
                401 => 'App Store Connect kimlik doğrulaması başarısız: Issuer ID, Key ID veya .p8 anahtarı hatalı.',
                403 => 'API anahtarının bu işlem için yetkisi yok.',
                default => 'App Store Connect hatası: HTTP '.$e->response->status(),
            }, previous: $e);
        }
    }

    public function discoverApps(StoreAccount $account): iterable
    {
        foreach ($this->client($account)->paginate('/v1/apps', [
            'fields[apps]' => 'name,bundleId',
            'limit' => 200,
        ]) as $page) {
            foreach ($page['data'] as $app) {
                yield new AppDescriptor(externalId: $app['id'], name: $app['attributes']['name']);
            }
        }
    }

    public function fetchMetrics(StoreApp $app, CarbonPeriod $period): iterable
    {
        $reports = new AnalyticsReports($this->client($app->storeAccount));
        $requestId = $reports->ongoingRequestId($app);

        if ($requestId === null) {
            // İstek yeni oluşturuldu; Apple raporları 1-2 gün içinde üretir.
            return [];
        }

        $from = CarbonImmutable::parse($period->getStartDate())->startOfDay();
        $to = CarbonImmutable::parse($period->getEndDate())->startOfDay();
        $reportIds = $reports->reportIds($requestId, AnalyticsReportMapper::reportNames());

        // Bir günün verisi birden fazla instance'a dağılabiliyor (geç gelen düzeltmeler).
        // processingDate >= from olan tüm instance'lar toplanmadan yazılırsa kısmi değer üzerine yazılır.
        $totals = [];

        foreach ($reportIds as $name => $reportId) {
            foreach ($reports->dailySegments($app, $name, $reportId, $from) as $raw) {
                $this->accumulate($totals, $name, $raw, $from, $to);
            }
        }

        return $this->toRows($totals);
    }

    public function fetchReviews(StoreApp $app, ?CarbonImmutable $since): iterable
    {
        $pages = $this->client($app->storeAccount)->paginate("/v1/apps/{$app->external_id}/customerReviews", [
            'sort' => '-createdDate',
            'limit' => 200,
            'include' => 'response',
            'fields[customerReviews]' => 'rating,title,body,reviewerNickname,createdDate,territory,response',
            'fields[customerReviewResponses]' => 'responseBody,lastModifiedDate',
        ]);

        foreach ($pages as $page) {
            $responses = collect($page['included'])
                ->where('type', 'customerReviewResponses')
                ->keyBy('id');

            foreach ($page['data'] as $review) {
                $attributes = $review['attributes'];
                $createdAt = CarbonImmutable::parse($attributes['createdDate']);

                // Liste yeniden eskiye sıralı; bilinen en yeni yorumdan eskisine gelince dur.
                // Aynı saniyedeki yorumları kaçırmamak için eşitlik dahil devam edilir.
                if ($since && $createdAt->lt($since)) {
                    return;
                }

                $response = $responses->get($review['relationships']['response']['data']['id'] ?? null);

                yield new ReviewData(
                    storeReviewId: $review['id'],
                    rating: (int) $attributes['rating'],
                    reviewedAt: $createdAt,
                    title: $attributes['title'] ?? null,
                    body: $attributes['body'] ?? null,
                    author: $attributes['reviewerNickname'] ?? null,
                    country: Territory::toAlpha2($attributes['territory'] ?? null),
                    replyBody: $response['attributes']['responseBody'] ?? null,
                    repliedAt: isset($response['attributes']['lastModifiedDate'])
                        ? CarbonImmutable::parse($response['attributes']['lastModifiedDate'])
                        : null,
                );
            }
        }
    }

    private function client(StoreAccount $account): AppStoreClient
    {
        return new AppStoreClient($account, $this->signer);
    }

    /**
     * @param  array<string, array<string, array<string, float>>>  $totals  tarih => ülke => metrik => değer
     */
    private function accumulate(array &$totals, string $report, RawReport $raw, CarbonImmutable $from, CarbonImmutable $to): void
    {
        try {
            // compress.zlib hem gzip'li hem düz dosyayı okur.
            $handle = fopen('compress.zlib://'.Storage::disk('local')->path($raw->path), 'r');
            $header = fgetcsv($handle, null, "\t", '"', '');
            $this->mapper->assertHeader($report, $header ?: []);

            while (($values = fgetcsv($handle, null, "\t", '"', '')) !== false) {
                if (count($values) !== count($header)) {
                    continue;
                }

                $row = array_combine($header, $values);
                $date = CarbonImmutable::parse($row['Date'])->startOfDay();

                if ($date->lt($from) || $date->gt($to)) {
                    continue;
                }

                $day = $date->toDateString();
                $country = Territory::toAlpha2($row['Territory'] ?? null);

                foreach ($this->mapper->map($report, $row) as $metric => $value) {
                    $totals[$day][DailyMetric::ALL_COUNTRIES][$metric] = ($totals[$day][DailyMetric::ALL_COUNTRIES][$metric] ?? 0) + $value;

                    if ($country) {
                        $totals[$day][$country][$metric] = ($totals[$day][$country][$metric] ?? 0) + $value;
                    }
                }
            }

            $raw->update(['processed_at' => now(), 'error' => null]);
        } catch (Throwable $e) {
            $raw->update(['error' => $e->getMessage()]);

            throw $e;
        } finally {
            if (isset($handle) && is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    /**
     * @param  array<string, array<string, array<string, float>>>  $totals
     * @return array<MetricRow>
     */
    private function toRows(array $totals): array
    {
        $rows = [];

        foreach ($totals as $day => $countries) {
            foreach ($countries as $country => $metrics) {
                foreach ($metrics as $metric => $value) {
                    $rows[] = new MetricRow(CarbonImmutable::parse($day), $metric, $value, $country);
                }
            }
        }

        return $rows;
    }
}
