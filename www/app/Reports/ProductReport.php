<?php

namespace App\Reports;

use App\Enums\Store;
use App\Models\DailyMetric;
use App\Models\Product;
use App\Models\Review;
use App\Models\StoreApp;
use App\Stores\Metric;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Ürün rapor sayfasının verisi: ürüne bağlı uygulamaların günlük metrikleri, mağaza bazında.
 * Toplamlar ülke kırılımsız (ZZ) satırlardan alınır; ülke satırlarıyla karıştırılırsa çift sayılır.
 */
class ProductReport
{
    public const STORE_COLORS = [
        'google_play' => '#34A853',
        'app_store' => '#0A84FF',
        'app_gallery' => '#CF0A2C',
    ];

    public readonly CarbonImmutable $from;

    public readonly CarbonImmutable $to;

    /** @var Collection<int, StoreApp> */
    private Collection $apps;

    public function __construct(public readonly Product $product, int $days)
    {
        // Mağazalar veriyi en erken ertesi gün yayınlıyor; aralık dünde biter.
        $this->to = CarbonImmutable::yesterday();
        $this->from = $this->to->subDays(max($days, 1) - 1);
        $this->apps = $product->storeApps()->orderBy('store')->get();
    }

    /**
     * @param  array<string, mixed>|null  $filters
     */
    public static function fromFilters(Product $product, ?array $filters): self
    {
        return new self($product, (int) ($filters['days'] ?? 30));
    }

    public static function metricFromFilters(?array $filters): string
    {
        $metric = $filters['metric'] ?? Metric::INSTALLS;

        return array_key_exists($metric, Metric::countLabels()) ? $metric : Metric::INSTALLS;
    }

    /**
     * @return Collection<int, StoreApp>
     */
    public function apps(): Collection
    {
        return $this->apps;
    }

    /**
     * Grafik için: her gün için mağaza başına değer.
     *
     * @return array{labels: array<string>, series: array<string, array<float>>}
     */
    public function series(string $metric): array
    {
        $days = collect(CarbonPeriod::create($this->from, $this->to))->map(fn ($day) => $day->toDateString());
        $values = $this->zzQuery($metric)
            ->selectRaw('store_apps.store, daily_metrics.date, SUM(daily_metrics.value) as value')
            ->groupBy('store_apps.store', 'daily_metrics.date')
            ->get()
            ->groupBy('store')
            ->map(fn ($rows) => $rows->mapWithKeys(fn ($row) => [CarbonImmutable::parse($row->date)->toDateString() => (float) $row->value]));

        $series = [];

        foreach ($this->apps->pluck('store')->unique() as $store) {
            $byDay = $values->get($store->value, collect());
            $series[$store->value] = $days->map(fn ($day) => $byDay->get($day, 0.0))->all();
        }

        return [
            'labels' => $days->map(fn ($day) => CarbonImmutable::parse($day)->format('d.m'))->all(),
            'series' => $series,
        ];
    }

    /**
     * Platform başına dönem toplamları ve en son veri günü.
     *
     * @return array<int, array{store: Store, apps: string, totals: array<string, float>, latest: ?string}>
     */
    public function summary(): array
    {
        $totals = DailyMetric::query()
            ->join('store_apps', 'store_apps.id', '=', 'daily_metrics.store_app_id')
            ->whereIn('daily_metrics.store_app_id', $this->apps->pluck('id'))
            ->where('daily_metrics.country', DailyMetric::ALL_COUNTRIES)
            ->whereIn('daily_metrics.metric', array_keys(Metric::countLabels()))
            ->whereBetween('daily_metrics.date', [$this->from->toDateString(), $this->to->toDateString()])
            ->selectRaw('store_apps.store, daily_metrics.metric, SUM(daily_metrics.value) as value')
            ->groupBy('store_apps.store', 'daily_metrics.metric')
            ->get()
            ->groupBy('store');

        $latest = DailyMetric::query()
            ->join('store_apps', 'store_apps.id', '=', 'daily_metrics.store_app_id')
            ->whereIn('daily_metrics.store_app_id', $this->apps->pluck('id'))
            ->selectRaw('store_apps.store, MAX(daily_metrics.date) as latest')
            ->groupBy('store_apps.store')
            ->pluck('latest', 'store');

        return $this->apps->groupBy(fn (StoreApp $app) => $app->store->value)
            ->map(fn (Collection $apps, string $store) => [
                'store' => Store::from($store),
                'apps' => $apps->pluck('name')->implode(', '),
                'totals' => ($totals->get($store) ?? collect())->mapWithKeys(fn ($row) => [$row->metric => (float) $row->value])->all(),
                'latest' => $latest->get($store) ? CarbonImmutable::parse($latest->get($store))->format('d.m.Y') : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{country: string, value: float}>
     */
    public function topCountries(string $metric, int $limit = 10): array
    {
        return DailyMetric::query()
            ->whereIn('store_app_id', $this->apps->pluck('id'))
            ->where('metric', $metric)
            ->where('country', '!=', DailyMetric::ALL_COUNTRIES)
            ->whereBetween('date', [$this->from->toDateString(), $this->to->toDateString()])
            ->selectRaw('country, SUM(value) as value')
            ->groupBy('country')
            ->havingRaw('SUM(value) > 0')
            ->orderByDesc('value')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => ['country' => $row->country, 'value' => (float) $row->value])
            ->all();
    }

    /**
     * @return Collection<int, Review>
     */
    public function latestReviews(int $limit = 8): Collection
    {
        return Review::query()
            ->with('storeApp')
            ->whereIn('store_app_id', $this->apps->pluck('id'))
            ->latest('reviewed_at')
            ->limit($limit)
            ->get();
    }

    private function zzQuery(string $metric)
    {
        return DailyMetric::query()
            ->join('store_apps', 'store_apps.id', '=', 'daily_metrics.store_app_id')
            ->whereIn('daily_metrics.store_app_id', $this->apps->pluck('id'))
            ->where('daily_metrics.metric', $metric)
            ->where('daily_metrics.country', DailyMetric::ALL_COUNTRIES)
            ->whereBetween('daily_metrics.date', [$this->from->toDateString(), $this->to->toDateString()]);
    }
}
