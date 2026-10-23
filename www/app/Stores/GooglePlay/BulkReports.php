<?php

namespace App\Stores\GooglePlay;

use App\Models\RawReport;
use App\Models\StoreApp;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/**
 * Play Console bulk raporları (gs://pubsite_prod_rev_<id>/stats/...).
 * Dosyalar aylık; içinde bulunulan ayın dosyası her gün yeniden yazılır (generation değişir).
 * İçerik UTF-16LE CSV.
 */
class BulkReports
{
    private const STORAGE_API = 'https://storage.googleapis.com/storage/v1';

    public function __construct(private readonly GoogleClient $client) {}

    /**
     * @return iterable<array{name: string, generation: string}>
     */
    public function objects(string $prefix): iterable
    {
        $query = ['prefix' => $prefix, 'fields' => 'items(name,generation),nextPageToken'];

        do {
            $page = $this->client->get(self::STORAGE_API.'/b/'.$this->client->bucket().'/o', $query);

            foreach ($page['items'] ?? [] as $item) {
                yield ['name' => $item['name'], 'generation' => (string) $item['generation']];
            }

            $query['pageToken'] = $page['nextPageToken'] ?? null;
        } while ($query['pageToken']);
    }

    /**
     * Örn. report=installs, dimension=country, month=2026-10 →
     * stats/installs/installs_com.example.app_202610_country.csv
     */
    public function monthly(StoreApp $app, string $report, string $dimension, CarbonImmutable $month): ?RawReport
    {
        $name = sprintf('stats/%1$s/%1$s_%2$s_%3$s_%4$s.csv', $report, $app->external_id, $month->format('Ym'), $dimension);

        foreach ($this->objects($name) as $object) {
            if ($object['name'] === $name) {
                return $this->store($app, $report, $month, $object);
            }
        }

        return null;
    }

    /**
     * @return iterable<array<string, string>>
     */
    public function rows(RawReport $raw): iterable
    {
        $lines = preg_split('/\r\n|\n|\r/', $this->decode(Storage::disk('local')->get($raw->path)));
        $header = null;

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = str_getcsv($line, ',', '"', '');

            if ($header === null) {
                $header = array_map('trim', $values);

                continue;
            }

            if (count($values) === count($header)) {
                yield array_combine($header, $values);
            }
        }
    }

    /**
     * @return array<string>
     */
    public function header(RawReport $raw): array
    {
        $first = strtok($this->decode(Storage::disk('local')->get($raw->path)), "\r\n");

        return array_map('trim', str_getcsv((string) $first, ',', '"', ''));
    }

    /**
     * @param  array{name: string, generation: string}  $object
     */
    private function store(StoreApp $app, string $report, CarbonImmutable $month, array $object): RawReport
    {
        // generation yola dahil: dosya güncellendiyse yeni kopya indirilir, değişmediyse indirme atlanır.
        $path = sprintf(
            'raw-reports/google_play/%d/%d/%s/%s_%s.csv',
            $app->store_account_id,
            $app->id,
            $report,
            pathinfo($object['name'], PATHINFO_FILENAME),
            $object['generation'],
        );

        $existing = RawReport::where('store_account_id', $app->store_account_id)->where('path', $path)->first();

        if ($existing && Storage::disk('local')->exists($path)) {
            return $existing;
        }

        $content = $this->client->download(
            self::STORAGE_API.'/b/'.$this->client->bucket().'/o/'.rawurlencode($object['name']).'?alt=media'
        );
        Storage::disk('local')->put($path, $content);

        return RawReport::updateOrCreate(
            ['store_account_id' => $app->store_account_id, 'checksum' => hash('sha256', $content)],
            [
                'store_app_id' => $app->id,
                'source' => 'google_play:'.$object['name'],
                'period_start' => $month->startOfMonth(),
                'period_end' => $month->endOfMonth(),
                'path' => $path,
                'processed_at' => null,
                'error' => null,
            ],
        );
    }

    private function decode(string $content): string
    {
        if (str_starts_with($content, "\xFF\xFE")) {
            return mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16LE');
        }

        if (str_starts_with($content, "\xFE\xFF")) {
            return mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16BE');
        }

        return str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;
    }
}
