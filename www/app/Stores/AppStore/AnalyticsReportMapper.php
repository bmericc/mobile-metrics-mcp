<?php

namespace App\Stores\AppStore;

use App\Stores\Metric;
use RuntimeException;

/**
 * Analytics Reports "Standard" raporlarının satırlarını normalize metriklere çevirir.
 * Standard raporlar gizlilik eşiği uygulanmamış toplam veridir; Detailed raporlar kullanılmaz.
 *
 * Unique Devices satırlar arası toplanır: Apple bu sütunu cihaz tipi / versiyon / ülke kırılımında
 * veriyor, bir cihaz gün içinde genelde tek kırılımda olduğundan toplam yaklaşık doğrudur.
 */
class AnalyticsReportMapper
{
    public const DOWNLOADS = 'App Downloads Standard';

    public const INSTALLS_DELETIONS = 'App Store Installation and Deletion Standard';

    public const SESSIONS = 'App Sessions Standard';

    public const ENGAGEMENT = 'App Store Discovery and Engagement Standard';

    public const CRASHES = 'App Crashes';

    /**
     * Rapor adı => zorunlu sütunlar (Date ve Territory hariç).
     */
    private const REQUIRED_COLUMNS = [
        self::DOWNLOADS => ['Download Type', 'Counts'],
        self::INSTALLS_DELETIONS => ['Event', 'Counts'],
        self::SESSIONS => ['Sessions', 'Unique Devices'],
        self::ENGAGEMENT => ['Event', 'Counts'],
        self::CRASHES => ['Crashes'],
    ];

    /**
     * @return array<string>
     */
    public static function reportNames(): array
    {
        return array_keys(self::REQUIRED_COLUMNS);
    }

    /**
     * @param  array<string>  $header
     */
    public function assertHeader(string $report, array $header): void
    {
        $missing = array_diff(['Date', ...self::REQUIRED_COLUMNS[$report]], $header);

        if ($missing) {
            throw new RuntimeException(sprintf(
                '"%s" raporunda beklenen sütunlar yok: %s. Gelen sütunlar: %s',
                $report,
                implode(', ', $missing),
                implode(', ', $header),
            ));
        }
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, float> metrik => değer
     */
    public function map(string $report, array $row): array
    {
        return match ($report) {
            self::DOWNLOADS => $this->downloads($row),
            self::INSTALLS_DELETIONS => $this->installsDeletions($row),
            self::SESSIONS => [
                Metric::SESSIONS => $this->number($row['Sessions']),
                Metric::ACTIVE_DEVICES => $this->number($row['Unique Devices']),
            ],
            self::ENGAGEMENT => strcasecmp($row['Event'], 'Page view') === 0
                ? [Metric::PAGE_VIEWS => $this->number($row['Counts'])]
                : [],
            self::CRASHES => [Metric::CRASHES => $this->number($row['Crashes'])],
        };
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, float>
     */
    private function downloads(array $row): array
    {
        $type = strtolower($row['Download Type']);
        $count = $this->number($row['Counts']);

        return match (true) {
            str_contains($type, 'update') => [Metric::UPDATES => $count],
            str_contains($type, 'first-time'), str_contains($type, 'redownload') => [Metric::INSTALLS => $count],
            // Restore (cihaz geri yükleme) kullanıcı kazanımı sayılmaz.
            default => [],
        };
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, float>
     */
    private function installsDeletions(array $row): array
    {
        return strcasecmp($row['Event'], 'Delete') === 0
            ? [Metric::UNINSTALLS => $this->number($row['Counts'])]
            : [];
    }

    private function number(?string $value): float
    {
        return (float) str_replace(',', '', (string) $value);
    }
}
