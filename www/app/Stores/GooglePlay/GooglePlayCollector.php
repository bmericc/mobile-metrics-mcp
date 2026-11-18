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
 * Kimlik: ya OAuth ("Google Play hesabı bağla", credentials.refresh_token) ya da service account
 * (credentials.service_account_json). credentials.reports_bucket hesabı tek bir Play geliştirici hesabına bağlar:
 * uygulamalar o bucket'taki raporlardan, henüz raporu olmayan yeni uygulamalar mağaza sayfasındaki geliştirici
 * kimliğinden bulunur. Bucket yoksa erişilebilen tüm uygulamalar alınır, kurulum raporları atlanır.
 * Gelir (earnings/ zip raporları) henüz toplanmıyor.
 */
class GooglePlayCollector implements StoreCollector
{
    private const PUBLISHER_API = 'https://androidpublisher.googleapis.com/androidpublisher/v3';

    private const REPORTING_API = 'https://playdeveloperreporting.googleapis.com/v1beta1';

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

        try {
            $client->get(self::REPORTING_API.'/apps:search', ['pageSize' => 1]);
        } catch (RequestException $e) {
            throw new RuntimeException(match ($e->response->status()) {
                403 => 'Bu Google hesabının Play Console\'da uygulama görme yetkisi yok.',
                default => 'Google Play Developer Reporting API hatası: HTTP '.$e->response->status(),
            }, previous: $e);
        }

        // Bucket opsiyonel: yoksa yalnızca vitals ve yorumlar toplanır, kurulum raporları atlanır.
        if (! $bucket = $client->bucket()) {
            return;
        }

        try {
            $client->get('https://storage.googleapis.com/storage/v1/b/'.$bucket.'/o', [
                'prefix' => 'stats/installs/',
                'maxResults' => 1,
            ]);
        } catch (RequestException $e) {
            throw new RuntimeException(match ($e->response->status()) {
                403 => "Rapor bucket'ı okunamıyor ({$bucket}). Bağlanan Google hesabının bu geliştirici hesabında Play Console'da toplu rapor indirme yetkisi olmalı.",
                404 => 'Bucket bulunamadı: '.$bucket,
                default => 'Google Cloud Storage hatası: HTTP '.$e->response->status(),
            }, previous: $e);
        }
    }

    public function discoverApps(StoreAccount $account): iterable
    {
        $client = new GoogleClient($account);
        $names = [];
        $query = ['pageSize' => 1000];

        // Reporting API, Google hesabının erişebildiği tüm geliştirici hesaplarındaki uygulamaları
        // (adlarıyla) listeliyor; hangi geliştirici hesabına ait olduklarını söylemiyor.
        do {
            $page = $client->get(self::REPORTING_API.'/apps:search', $query);

            foreach ($page['apps'] ?? [] as $app) {
                $names[$app['packageName']] = $app['displayName'] ?? $app['packageName'];
            }

            $query['pageToken'] = $page['nextPageToken'] ?? null;
        } while ($query['pageToken']);

        if ($client->bucket()) {
            // Geliştirici hesabının uygulamaları = bucket'ında güncel raporu olan paketler. Başka hesaba
            // aktarılan uygulamanın eski ayları eski bucket'ta kalıyor; yalnızca son iki ay sayılır ki
            // uygulama iki hesap arasında gidip gelmesin.
            $apps = [];
            $since = now()->subMonthNoOverflow()->format('Ym');

            foreach ((new BulkReports($client))->objects('stats/installs/installs_') as $object) {
                if (preg_match('#^stats/installs/installs_(.+)_(\d{6})_overview\.csv$#', $object['name'], $match) && $match[2] >= $since) {
                    $apps[$match[1]] = $names[$match[1]] ?? $match[1];
                }
            }

            $apps += $this->newAppsOfSameDeveloper($account, $names, $apps);
        } else {
            // Bucket'sız hesap, bucket'ı tanımlı başka bir Play hesabının sahiplendiği uygulamaları almaz.
            $owned = StoreApp::query()
                ->where('workspace_id', $account->workspace_id)
                ->where('store', $account->store)
                ->where('store_account_id', '!=', $account->id)
                ->pluck('external_id')
                ->flip();
            $apps = array_diff_key($names, $owned->all());
        }

        foreach ($apps as $package => $name) {
            yield new AppDescriptor(externalId: $package, name: $name);
        }
    }

    /**
     * Yeni yayınlanan uygulamanın bucket'ta henüz kurulum raporu olmuyor (Play aylık raporu kurulum verisi
     * oluştuktan günler sonra yazıyor). Hiçbir hesabın sahiplenmediği böyle bir uygulama, mağaza sayfasındaki
     * geliştirici kimliği bu hesabın raporlu uygulamalarınınkiyle aynıysa bu hesaba alınır.
     *
     * @param  array<string, string>  $names  Reporting API'nin listelediği tüm uygulamalar
     * @param  array<string, string>  $apps  bucket raporlarından bulunan uygulamalar
     * @return array<string, string>
     */
    private function newAppsOfSameDeveloper(StoreAccount $account, array $names, array $apps): array
    {
        $known = StoreApp::query()
            ->where('workspace_id', $account->workspace_id)
            ->where('store', $account->store)
            ->pluck('external_id')
            ->flip()
            ->all();
        $candidates = array_diff_key($names, $apps, $known);

        if (! $candidates || ! $apps) {
            return [];
        }

        $listing = new PlayListing;
        $developerId = null;

        foreach (array_keys($apps) as $package) {
            if ($developerId = $listing->developerId($package)) {
                break;
            }
        }

        if (! $developerId) {
            return [];
        }

        return array_filter(
            $candidates,
            fn (string $package) => $listing->developerId($package) === $developerId,
            ARRAY_FILTER_USE_KEY,
        );
    }

    public function fetchMetrics(StoreApp $app, CarbonPeriod $period): iterable
    {
        $client = new GoogleClient($app->storeAccount);
        $reports = new BulkReports($client);
        $from = CarbonImmutable::parse($period->getStartDate())->startOfDay();
        $to = CarbonImmutable::parse($period->getEndDate())->startOfDay();

        $rows = [];

        for ($month = $from->startOfMonth(); $client->bucket() && $month->lte($to); $month = $month->addMonth()) {
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
