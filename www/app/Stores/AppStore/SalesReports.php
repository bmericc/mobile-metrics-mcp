<?php

namespace App\Stores\AppStore;

use App\Models\RawReport;
use App\Models\StoreAccount;
use App\Models\StoreApp;
use App\Stores\Metric;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Storage;

/**
 * Sales and Trends günlük özet raporu (GET /v1/salesReports, SALES / SUMMARY / DAILY).
 * Analytics Reports'un aksine Admin istemez: "Sales and Reports" rolü ve vendor number yeter.
 * Rapor tüm hesabı kapsar; günlük dosya hesap başına bir kez indirilip uygulamalar arasında paylaşılır.
 *
 * Yalnızca indirme ve güncelleme sayıları alınır. Gelir satırları farklı para birimlerinde olduğu için
 * toplanmaz; gösterim, oturum, kaldırma ve çökme bu raporda yok.
 */
class SalesReports
{
    /**
     * Product Type Identifier ön ekleri: 1/F1 ilk indirme, 3/F3 yeniden indirme, 7/F7 güncelleme
     * (sonek F/T: universal/iPad). Uygulama içi satın almalar (IA*, FI*) sayılmaz.
     */
    private const PRODUCT_TYPES = [
        '1' => Metric::INSTALLS, '1F' => Metric::INSTALLS, '1T' => Metric::INSTALLS, 'F1' => Metric::INSTALLS,
        '3' => Metric::INSTALLS, '3F' => Metric::INSTALLS, '3T' => Metric::INSTALLS, 'F3' => Metric::INSTALLS,
        '7' => Metric::UPDATES, '7F' => Metric::UPDATES, '7T' => Metric::UPDATES, 'F7' => Metric::UPDATES,
    ];

    public function __construct(private readonly AppStoreClient $client) {}

    public static function vendorNumber(StoreAccount $account): ?string
    {
        $vendor = trim((string) ($account->credentials['vendor_number'] ?? ''));

        return $vendor !== '' ? $vendor : null;
    }

    /**
     * @return iterable<array{country: string, metric: string, value: float}>
     */
    public function rows(StoreApp $app, CarbonImmutable $date): iterable
    {
        $raw = $this->daily($app->storeAccount, $date);
        $content = $raw ? Storage::disk('local')->get($raw->path) : '';

        if ($content === '') {
            return;
        }

        $lines = preg_split('/\r\n|\n|\r/', $content);
        $header = str_getcsv(array_shift($lines), "\t", '"', '');

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = str_getcsv($line, "\t", '"', '');

            if (count($values) !== count($header)) {
                continue;
            }

            $row = array_combine($header, $values);
            $metric = self::PRODUCT_TYPES[$row['Product Type Identifier'] ?? ''] ?? null;

            if ($metric && ($row['Apple Identifier'] ?? null) === $app->external_id) {
                yield ['country' => strtoupper($row['Country Code']), 'metric' => $metric, 'value' => (float) $row['Units']];
            }
        }
    }

    /**
     * Günün raporu; satış olmayan gün için boş dosya saklanır ki tekrar sorulmasın.
     * Henüz yayınlanmamış gün (son 2 gün) için null döner ve sonraki çalışmada yeniden denenir.
     */
    public function daily(StoreAccount $account, CarbonImmutable $date): ?RawReport
    {
        $path = sprintf('raw-reports/app_store/%d/sales/%s.tsv', $account->id, $date->toDateString());
        $existing = RawReport::where('store_account_id', $account->id)->where('path', $path)->first();

        if ($existing && Storage::disk('local')->exists($path)) {
            return $existing;
        }

        try {
            $content = $this->client->raw('/v1/salesReports', [
                'filter[frequency]' => 'DAILY',
                'filter[reportType]' => 'SALES',
                'filter[reportSubType]' => 'SUMMARY',
                'filter[version]' => '1_1',
                'filter[vendorNumber]' => self::vendorNumber($account),
                'filter[reportDate]' => $date->toDateString(),
            ]);
            $content = str_starts_with($content, "\x1f\x8b") ? gzdecode($content) : $content;
        } catch (RequestException $e) {
            if ($e->response->status() !== 404) {
                throw $e;
            }

            // 404: o gün satış yok ya da son günlerin raporu henüz yayınlanmadı (sonra yeniden denenir).
            if ($date->gt(today()->subDays(3))) {
                return null;
            }

            $content = '';
        }

        Storage::disk('local')->put($path, $content);

        return RawReport::updateOrCreate(
            ['store_account_id' => $account->id, 'path' => $path],
            [
                'checksum' => hash('sha256', $content),
                'source' => 'app_store:salesReports:'.$date->toDateString(),
                'period_start' => $date,
                'period_end' => $date,
                'processed_at' => now(),
                'error' => null,
            ],
        );
    }
}
