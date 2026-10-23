<?php

namespace App\Stores\GooglePlay;

use App\Stores\Metric;
use RuntimeException;

/**
 * Bulk rapor sütunlarını normalize metriklere çevirir. Google, eski "Daily User Installs"
 * sütunlarının yerine "Install events" vb. getirdi; ikisi de desteklenir, yenisi önceliklidir.
 */
class BulkReportMapper
{
    /**
     * Rapor => metrik => aday sütunlar (öncelik sırasıyla).
     */
    private const COLUMNS = [
        'installs' => [
            Metric::INSTALLS => ['Install events', 'Daily User Installs'],
            Metric::UNINSTALLS => ['Uninstall events', 'Daily User Uninstalls'],
            Metric::UPDATES => ['Update events', 'Daily Device Upgrades'],
            Metric::ACTIVE_DEVICES => ['Active Device Installs'],
        ],
        // Günlük ortalama az yorumlu günlerde boş/NA; kümülatif ortalama mağazada görünen puandır.
        'ratings' => [
            Metric::RATING_AVG => ['Total Average Rating'],
        ],
        'crashes' => [
            Metric::CRASHES => ['Daily Crashes'],
        ],
    ];

    /**
     * Rapor => metrik => sütun eşlemesini header'a göre çözer.
     *
     * @param  array<string>  $header
     * @return array<string, string>
     */
    public function resolve(string $report, array $header): array
    {
        $resolved = [];

        foreach (self::COLUMNS[$report] as $metric => $candidates) {
            foreach ($candidates as $column) {
                if (in_array($column, $header, true)) {
                    $resolved[$metric] = $column;

                    break;
                }
            }
        }

        if (! in_array('Date', $header, true) || $resolved === []) {
            throw new RuntimeException(sprintf(
                '"%s" raporunda beklenen sütunlar yok. Gelen sütunlar: %s',
                $report,
                implode(', ', $header),
            ));
        }

        return $resolved;
    }

    /**
     * @param  array<string, string>  $columns  resolve() çıktısı
     * @param  array<string, string>  $row
     * @return array<string, float>
     */
    public function map(array $columns, array $row): array
    {
        $values = [];

        foreach ($columns as $metric => $column) {
            $value = trim($row[$column] ?? '');

            if ($value !== '' && is_numeric($value)) {
                $values[$metric] = (float) $value;
            }
        }

        return $values;
    }
}
