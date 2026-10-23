<?php

namespace App\Stores\Data;

use App\Models\DailyMetric;
use Carbon\CarbonImmutable;

final readonly class MetricRow
{
    public function __construct(
        public CarbonImmutable $date,
        public string $metric,
        public float $value,
        public string $country = DailyMetric::ALL_COUNTRIES,
    ) {}
}
