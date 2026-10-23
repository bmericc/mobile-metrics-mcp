<?php

namespace App\Filament\App\Resources\Products\Widgets;

use App\Enums\Store;
use App\Models\Product;
use App\Reports\ProductReport;
use App\Stores\Metric;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Seçilen metriğin günlük değeri; ürüne bağlı her platform ayrı çizgi.
 */
class ProductMetricsChart extends ChartWidget
{
    use InteractsWithPageFilters;

    public ?Product $record = null;

    // Rapor sayfası zaten bu widget'lar için açılıyor; ayrı bir yükleme turu beklemesin.
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    public function getHeading(): string|Htmlable|null
    {
        return Metric::countLabels()[ProductReport::metricFromFilters($this->pageFilters)].' — platformlara göre günlük';
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $report = ProductReport::fromFilters($this->record, $this->pageFilters);
        $data = $report->series(ProductReport::metricFromFilters($this->pageFilters));

        return [
            'labels' => $data['labels'],
            'datasets' => collect($data['series'])->map(fn (array $values, string $store) => [
                'label' => Store::from($store)->label(),
                'data' => $values,
                'borderColor' => ProductReport::STORE_COLORS[$store],
                'backgroundColor' => ProductReport::STORE_COLORS[$store].'22',
                'pointRadius' => 2,
                'tension' => 0.25,
            ])->values()->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => true, 'position' => 'bottom']],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
            'interaction' => ['mode' => 'index', 'intersect' => false],
        ];
    }
}
