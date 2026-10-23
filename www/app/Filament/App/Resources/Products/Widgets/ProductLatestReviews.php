<?php

namespace App\Filament\App\Resources\Products\Widgets;

use App\Models\Product;
use App\Reports\ProductReport;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

class ProductLatestReviews extends Widget
{
    use InteractsWithPageFilters;

    public ?Product $record = null;

    // Rapor sayfası zaten bu widget'lar için açılıyor; ayrı bir yükleme turu beklemesin.
    protected static bool $isLazy = false;

    protected string $view = 'filament.product-report.latest-reviews';

    protected int|string|array $columnSpan = 1;

    protected function getViewData(): array
    {
        return [
            'report' => ProductReport::fromFilters($this->record, $this->pageFilters),
            'metric' => ProductReport::metricFromFilters($this->pageFilters),
        ];
    }
}
