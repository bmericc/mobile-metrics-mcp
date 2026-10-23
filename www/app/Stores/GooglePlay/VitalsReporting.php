<?php

namespace App\Stores\GooglePlay;

use App\Models\StoreApp;
use App\Stores\Data\MetricRow;
use App\Stores\Metric;
use Carbon\CarbonImmutable;

/**
 * Play Developer Reporting API (Android vitals). Oranlar 0-1 arası kesir olarak döner ve öyle saklanır.
 * Günlük kırılım America/Los_Angeles saat dilimini şart koşar.
 */
class VitalsReporting
{
    private const API = 'https://playdeveloperreporting.googleapis.com/v1beta1';

    private const TIME_ZONE = 'America/Los_Angeles';

    /**
     * Metric set => [API metrik adı => normalize metrik].
     */
    private const METRIC_SETS = [
        'crashRateMetricSet' => ['crashRate' => Metric::CRASH_RATE],
        'anrRateMetricSet' => ['anrRate' => Metric::ANR_RATE],
    ];

    public function __construct(private readonly GoogleClient $client) {}

    /**
     * @return iterable<MetricRow>
     */
    public function fetch(StoreApp $app, CarbonImmutable $from, CarbonImmutable $to): iterable
    {
        foreach (self::METRIC_SETS as $set => $metrics) {
            $base = self::API."/apps/{$app->external_id}/{$set}";

            // Henüz işlenmemiş günler sorgulanırsa API hata döner; bitişi tazelik sınırına çek.
            $latestEnd = $this->latestDailyEnd($base);

            if (! $latestEnd) {
                continue;
            }

            // endTime hariç tutulur.
            $end = $to->addDay()->min($latestEnd);

            if ($end->lte($from)) {
                continue;
            }

            $body = [
                'timelineSpec' => [
                    'aggregationPeriod' => 'DAILY',
                    'startTime' => $this->dateTime($from),
                    'endTime' => $this->dateTime($end),
                ],
                'metrics' => array_keys($metrics),
                'pageSize' => 1000,
            ];

            do {
                $page = $this->client->post("{$base}:query", $body);

                foreach ($page['rows'] ?? [] as $row) {
                    $date = $this->date($row['startTime']);

                    foreach ($row['metrics'] ?? [] as $metric) {
                        $value = $metric['decimalValue']['value'] ?? null;

                        if (isset($metrics[$metric['metric']]) && is_numeric($value)) {
                            yield new MetricRow($date, $metrics[$metric['metric']], (float) $value);
                        }
                    }
                }

                $body['pageToken'] = $page['nextPageToken'] ?? null;
            } while ($body['pageToken']);
        }
    }

    private function latestDailyEnd(string $base): ?CarbonImmutable
    {
        foreach ($this->client->get($base)['freshnessInfo']['freshnesses'] ?? [] as $freshness) {
            if ($freshness['aggregationPeriod'] === 'DAILY') {
                return $this->date($freshness['latestEndTime']);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function dateTime(CarbonImmutable $date): array
    {
        return [
            'year' => $date->year,
            'month' => $date->month,
            'day' => $date->day,
            'timeZone' => ['id' => self::TIME_ZONE],
        ];
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function date(array $value): CarbonImmutable
    {
        return CarbonImmutable::create($value['year'], $value['month'], $value['day']);
    }
}
