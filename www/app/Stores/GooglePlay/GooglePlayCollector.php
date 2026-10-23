<?php

namespace App\Stores\GooglePlay;

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
use RuntimeException;
use Throwable;

/**
 * Kaynaklar:
 *  - Kurulum / kaldırma / güncelleme / aktif cihaz, puan, crash sayısı: Play Console bulk raporları (GCS)
 *  - Crash / ANR oranı: Play Developer Reporting API
 *  - Yorumlar: Android Publisher API reviews.list (yalnızca son 7 günde yazılan/güncellenen yorumlar)
 *
 * Kimlik: credentials.service_account_json + credentials.reports_bucket. Service account,
 * Play Console > Kullanıcılar ve izinler'den hesaba davet edilmiş ve rapor görme yetkisi almış olmalı.
 * Gelir (earnings/ zip raporları) henüz toplanmıyor.
 */
class GooglePlayCollector implements StoreCollector
{
    private const PUBLISHER_API = 'https://androidpublisher.googleapis.com/androidpublisher/v3';

    /**
     * [rapor, boyut]; country boyutu ülke kırılımı, overview toplamı (ZZ) verir.
     */
    private const BULK_FILES = [
        ['installs', 'overview'],
        ['installs', 'country'],
        ['ratings', 'overview'],
        ['crashes', 'overview'],
    ];

    public function __construct(private readonly BulkReportMapper $mapper) {}

    public function verify(StoreAccount $account): void
    {
        $client = new GoogleClient($account);

        if ($client->bucket() === '') {
            throw new RuntimeException('Rapor bucket adı girilmemiş.');
        }

        try {
            $client->get('https://storage.googleapis.com/storage/v1/b/'.$client->bucket().'/o', [
                'prefix' => 'stats/installs/',
                'maxResults' => 1,
            ]);
        } catch (RequestException $e) {
            throw new RuntimeException(match ($e->response->status()) {
                403 => 'Service account bu bucket\'ı okuyamıyor. Play Console\'da hesaba davet edilip rapor görme yetkisi verilmeli.',
                404 => 'Bucket bulunamadı: '.$client->bucket(),
                default => 'Google Cloud Storage hatası: HTTP '.$e->response->status(),
            }, previous: $e);
        }
    }

    public function discoverApps(StoreAccount $account): iterable
    {
        // Android Publisher API uygulama listelemiyor; paketler rapor dosya adlarından çıkarılır.
        $packages = [];

        foreach ((new BulkReports(new GoogleClient($account)))->objects('stats/installs/installs_') as $object) {
            if (preg_match('#^stats/installs/installs_(.+)_\d{6}_overview\.csv$#', $object['name'], $match)) {
                $packages[$match[1]] = true;
            }
        }

        foreach (array_keys($packages) as $package) {
            yield new AppDescriptor(externalId: $package, name: $package);
        }
    }

    public function fetchMetrics(StoreApp $app, CarbonPeriod $period): iterable
    {
        $client = new GoogleClient($app->storeAccount);
        $reports = new BulkReports($client);
        $from = CarbonImmutable::parse($period->getStartDate())->startOfDay();
        $to = CarbonImmutable::parse($period->getEndDate())->startOfDay();

        $rows = [];

        for ($month = $from->startOfMonth(); $month->lte($to); $month = $month->addMonth()) {
            foreach (self::BULK_FILES as [$report, $dimension]) {
                $raw = $reports->monthly($app, $report, $dimension, $month);

                if ($raw) {
                    array_push($rows, ...$this->parse($reports, $raw, $report, $dimension, $from, $to));
                }
            }
        }

        array_push($rows, ...(new VitalsReporting($client))->fetch($app, $from, $to));

        return $rows;
    }

    public function fetchReviews(StoreApp $app, ?CarbonImmutable $since): iterable
    {
        $client = new GoogleClient($app->storeAccount);
        $query = ['maxResults' => 100];

        // API zaten yalnızca son 7 günü döndürüyor; yorumlar düzenlenebildiği için hepsi upsert edilir.
        do {
            $page = $client->get(self::PUBLISHER_API."/applications/{$app->external_id}/reviews", $query);

            foreach ($page['reviews'] ?? [] as $review) {
                $user = collect($review['comments'] ?? [])->pluck('userComment')->filter()->first();
                $developer = collect($review['comments'] ?? [])->pluck('developerComment')->filter()->first();

                if (! $user) {
                    continue;
                }

                yield new ReviewData(
                    storeReviewId: $review['reviewId'],
                    rating: (int) $user['starRating'],
                    reviewedAt: $this->timestamp($user['lastModified']),
                    body: trim($user['text'] ?? '') ?: null,
                    author: $review['authorName'] ?? null,
                    language: $user['reviewerLanguage'] ?? null,
                    // reviewerLanguage "tr_TR" gibi gelir; ülke kısmı yaklaşık ülke bilgisidir.
                    country: str_contains($user['reviewerLanguage'] ?? '', '_')
                        ? Territory::toAlpha2(str($user['reviewerLanguage'])->afterLast('_')->toString())
                        : null,
                    appVersion: $user['appVersionName'] ?? null,
                    replyBody: $developer['text'] ?? null,
                    repliedAt: isset($developer['lastModified']) ? $this->timestamp($developer['lastModified']) : null,
                );
            }

            $query['token'] = $page['tokenPagination']['nextPageToken'] ?? null;
        } while ($query['token']);
    }

    /**
     * @return array<MetricRow>
     */
    private function parse(BulkReports $reports, RawReport $raw, string $report, string $dimension, CarbonImmutable $from, CarbonImmutable $to): array
    {
        try {
            $columns = $this->mapper->resolve($report, $reports->header($raw));
            $rows = [];

            foreach ($reports->rows($raw) as $row) {
                $date = CarbonImmutable::parse($row['Date'])->startOfDay();

                if ($date->lt($from) || $date->gt($to)) {
                    continue;
                }

                $country = $dimension === 'country'
                    ? Territory::toAlpha2($row['Country'] ?? null)
                    : DailyMetric::ALL_COUNTRIES;

                if (! $country) {
                    continue;
                }

                foreach ($this->mapper->map($columns, $row) as $metric => $value) {
                    $rows[] = new MetricRow($date, $metric, $value, $country);
                }
            }

            $raw->update(['processed_at' => now(), 'error' => null]);

            return $rows;
        } catch (Throwable $e) {
            $raw->update(['error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * @param  array{seconds: string|int, nanos?: int}  $value
     */
    private function timestamp(array $value): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestampUTC((int) $value['seconds']);
    }
}
