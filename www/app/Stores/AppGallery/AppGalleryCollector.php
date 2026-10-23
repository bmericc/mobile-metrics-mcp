<?php

namespace App\Stores\AppGallery;

use App\Models\DailyMetric;
use App\Models\RawReport;
use App\Models\StoreAccount;
use App\Models\StoreApp;
use App\Stores\Contracts\StoreCollector;
use App\Stores\Csv;
use App\Stores\Data\AppDescriptor;
use App\Stores\Data\MetricRow;
use App\Stores\Data\ReviewData;
use App\Stores\Metric;
use App\Stores\Territory;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * AppGallery Connect API:
 *  - Uygulamalar: GET /api/publish/v2/appid-list?packageName=... (tüm uygulamaları listeleyen uç yok;
 *    paket adları credentials.package_names alanından gelir)
 *  - Metrikler: GET /api/report/distribution-operation-quality/v1/appDownloadExport/{appId} → CSV URL
 *  - Yorumlar: GET /api/reviews/v1/manage/dev/reviews (ülke listesi zorunlu, site alan adına bağlı;
 *    yalnızca API client ile — Service Account kabul edilmiyor)
 *
 * Kimlik: AGC > Kullanıcılar ve izinler > Connect API'de proje seçilmeden (N/A) oluşturulan API client.
 *   credentials: client_id, client_secret, package_names, review_site (cn|de|sg|ru), review_countries
 */
class AppGalleryCollector implements StoreCollector
{
    private const REPORT_PATH = '/api/report/distribution-operation-quality/v1/appDownloadExport/';

    /**
     * Normalize metrik => aday rapor sütunları (en-US), öncelik sırasıyla. Huawei'nin gerçek dosyası
     * dokümandaki adlardan farklı ("Successful updates", "Details UV ..."); ikisi de desteklenir.
     * "Details UV" tekil ziyaretçi sayısıdır, sayfa görüntüleme yerine en yakın karşılık olarak alınır.
     */
    private const COLUMNS = [
        Metric::INSTALLS => ['New downloads'],
        Metric::UPDATES => ['Successful updates', 'Update downloads'],
        Metric::UNINSTALLS => ['Uninstalls', 'Total uninstalls'],
        Metric::PAGE_VIEWS => ['App details page views', 'Details UV (reported by client)'],
        Metric::IMPRESSIONS => ['Valid impressions', 'Impressions'],
    ];

    public function verify(StoreAccount $account): void
    {
        $client = new AgcClient($account);
        $packages = $this->packageNames($account);

        if ($packages === []) {
            throw new RuntimeException('En az bir paket adı girilmeli; AppGallery Connect uygulama listeleme sunmuyor.');
        }

        $client->get('/api/publish/v2/appid-list', ['packageName' => $packages[0]]);
    }

    public function discoverApps(StoreAccount $account): iterable
    {
        $client = new AgcClient($account);

        foreach (array_chunk($this->packageNames($account), 50) as $chunk) {
            $response = $client->get('/api/publish/v2/appid-list', ['packageName' => implode(',', $chunk)]);

            // key = uygulama adı, value = appId
            foreach ($response['appids'] ?? [] as $app) {
                yield new AppDescriptor(externalId: (string) $app['value'], name: $app['key']);
            }
        }
    }

    public function fetchMetrics(StoreApp $app, CarbonPeriod $period): iterable
    {
        $client = new AgcClient($app->storeAccount);
        $from = CarbonImmutable::parse($period->getStartDate())->startOfDay();
        $to = CarbonImmutable::parse($period->getEndDate())->startOfDay();
        $rows = [];

        // Günlük toplam (ZZ) tek raporda gelir; ilk sütun tarih (Huawei "Date" ya da "Time" yazıyor).
        $report = $this->parse($this->report($client, $app, 'date', $from, $to));

        foreach ($report['rows'] as $row) {
            $date = $this->date($row[$report['dimension']]);

            if ($date && $date->between($from, $to)) {
                array_push($rows, ...$this->metricRows($row, $report['columns'], $date, DailyMetric::ALL_COUNTRIES));
            }
        }

        // countryId gruplaması tarih kırılımı vermediği için ülke verisi gün gün istenir; ilk sütun ülke.
        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $report = $this->parse($this->report($client, $app, 'countryId', $day, $day));

            foreach ($report['rows'] as $row) {
                $country = Territory::toAlpha2($row[$report['dimension']]) ?? Territory::fromName($row[$report['dimension']]);

                if ($country) {
                    array_push($rows, ...$this->metricRows($row, $report['columns'], $day, $country));
                }
            }
        }

        return $rows;
    }

    public function fetchReviews(StoreApp $app, ?CarbonImmutable $since): iterable
    {
        $credentials = $app->storeAccount->credentials;
        $countries = $this->list($credentials['review_countries'] ?? '');

        if ($countries === []) {
            // Ülke listesi zorunlu; girilmemişse yorum senkronizasyonu atlanır.
            return;
        }

        // Comments API Service Account token'ını reddediyor (401 "client token auth failed"); yalnızca
        // API client (client_id + client_secret) ile çalışıyor. Hesapta API client yoksa yorumlar atlanır.
        $client = new AgcClient($app->storeAccount, apiClientOnly: true);

        if (! $client->hasApiClient()) {
            return;
        }

        $site = $credentials['review_site'] ?? 'cn';
        // API en fazla 6 aylık aralık kabul ediyor.
        $begin = ($since ?? now()->subMonths(6))->max(now()->subMonths(6)->addDay());
        $page = 1;

        do {
            $response = $client->get('/api/reviews/v1/manage/dev/reviews', [
                'appId' => $app->external_id,
                'beginTime' => $begin->getTimestampMs(),
                'endTime' => now()->getTimestampMs(),
                'countries' => implode(',', $countries),
                'sort' => 0,
                'page' => $page++,
                'limit' => 100,
            ], $site);

            foreach ($response['data']['reviewList'] ?? [] as $review) {
                yield new ReviewData(
                    storeReviewId: $review['reviewId'],
                    rating: (int) $review['rating'],
                    reviewedAt: CarbonImmutable::createFromTimestampMsUTC($review['dateline']),
                    body: $review['content'] ?? null,
                    country: Territory::toAlpha2($review['countryCode'] ?? null),
                    appVersion: $review['apkVersion'] ?? null,
                    replyBody: $review['devReplyContent'] ?? null,
                );
            }
        } while (($response['data']['hasNext'] ?? 0) == 1);
    }

    private function report(AgcClient $client, StoreApp $app, string $groupBy, CarbonImmutable $from, CarbonImmutable $to): RawReport
    {
        $response = $client->get(self::REPORT_PATH.$app->external_id, [
            'language' => 'en-US',
            'startTime' => $from->format('Ymd'),
            'endTime' => $to->format('Ymd'),
            'groupBy' => $groupBy,
            'exportType' => 'CSV',
        ]);

        if (empty($response['fileURL'])) {
            throw new RuntimeException('AppGallery rapor URL\'i dönmedi.');
        }

        // Huawei geç gelen veriyi güncellediği için rapor her çalışmada yeniden indirilir.
        $content = $client->download($response['fileURL']);
        $path = sprintf(
            'raw-reports/app_gallery/%d/%d/%s_%s_%s.csv',
            $app->store_account_id,
            $app->id,
            $groupBy,
            $from->format('Ymd'),
            $to->format('Ymd'),
        );
        Storage::disk('local')->put($path, $content);

        return RawReport::updateOrCreate(
            ['store_account_id' => $app->store_account_id, 'path' => $path],
            [
                'store_app_id' => $app->id,
                'checksum' => hash('sha256', $content),
                'source' => 'app_gallery:appDownloadExport:'.$groupBy,
                'period_start' => $from,
                'period_end' => $to,
                'processed_at' => null,
                'error' => null,
            ],
        );
    }

    /**
     * Başlık, bilinen bir metrik sütunu içeren ilk satırdır; ilk sütunu boyuttur (tarih ya da ülke).
     *
     * @return array{dimension: ?string, columns: array<string, string>, rows: array<array<string, string>>}
     */
    private function parse(RawReport $raw): array
    {
        $known = array_merge(...array_values(self::COLUMNS));

        try {
            $content = Storage::disk('local')->get($raw->path);
            $csv = Csv::parse($content, fn (array $values) => array_intersect($known, $values) !== []);

            if ($csv['header'] === []) {
                // Veri olmayan günlerde dosya boş gelebilir.
                if (trim(Csv::decode($content)) === '') {
                    $raw->update(['processed_at' => now(), 'error' => null]);

                    return ['dimension' => null, 'columns' => [], 'rows' => []];
                }

                throw new RuntimeException('AppGallery raporunda beklenen sütunlar yok. İlk satır: '.strtok(Csv::decode($content), "\r\n"));
            }

            $columns = [];

            foreach (self::COLUMNS as $metric => $candidates) {
                foreach ($candidates as $column) {
                    if (in_array($column, $csv['header'], true)) {
                        $columns[$metric] = $column;

                        break;
                    }
                }
            }

            $raw->update(['processed_at' => now(), 'error' => null]);

            return ['dimension' => $csv['header'][0], 'columns' => $columns, 'rows' => $csv['rows']];
        } catch (Throwable $e) {
            $raw->update(['error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, string>  $columns  metrik => sütun
     * @return array<MetricRow>
     */
    private function metricRows(array $row, array $columns, CarbonImmutable $date, string $country): array
    {
        $rows = [];

        foreach ($columns as $metric => $column) {
            $value = str_replace([',', ' ', '%'], '', $row[$column] ?? '');

            if ($value !== '' && is_numeric($value)) {
                $rows[] = new MetricRow($date, $metric, (float) $value, $country);
            }
        }

        return $rows;
    }

    private function date(string $value): ?CarbonImmutable
    {
        $digits = preg_replace('/\D/', '', $value);

        return strlen($digits) === 8 ? CarbonImmutable::createFromFormat('!Ymd', $digits) : null;
    }

    /**
     * @return array<string>
     */
    private function packageNames(StoreAccount $account): array
    {
        return $this->list($account->credentials['package_names'] ?? '');
    }

    /**
     * @return array<string>
     */
    private function list(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $value))));
    }
}
