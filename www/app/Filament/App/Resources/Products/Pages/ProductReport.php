<?php

namespace App\Filament\App\Resources\Products\Pages;

use App\Filament\App\Resources\Products\ProductResource;
use App\Filament\App\Resources\Products\Widgets\ProductLatestReviews;
use App\Filament\App\Resources\Products\Widgets\ProductMetricsChart;
use App\Filament\App\Resources\Products\Widgets\ProductPlatformSummary;
use App\Filament\App\Resources\Products\Widgets\ProductTopCountries;
use App\Stores\Metric;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Ürün raporu: ürüne bağlı platformlar tek grafikte, altında platform özeti, ülkeler ve son yorumlar.
 * Filtreler (gün aralığı, metrik) sayfada tutulur ve widget'lara pageFilters olarak geçer.
 */
class ProductReport extends Page
{
    use HasFiltersForm;
    use InteractsWithRecord;

    protected static string $resource = ProductResource::class;

    protected static ?string $breadcrumb = 'Rapor';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->name.' — Rapor';
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('days')
                ->label('Dönem')
                ->options([7 => 'Son 7 gün', 14 => 'Son 14 gün', 30 => 'Son 30 gün', 90 => 'Son 90 gün'])
                ->default(30)
                ->selectablePlaceholder(false),
            Select::make('metric')
                ->label('Grafik metriği')
                ->options(Metric::countLabels())
                ->default(Metric::INSTALLS)
                ->selectablePlaceholder(false),
        ])->columns(['md' => 2, 'xl' => 4]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('filtersForm'),
            Grid::make(['default' => 1, 'lg' => 2])
                ->schema(fn (): array => $this->getWidgetsSchemaComponents([
                    ProductMetricsChart::class,
                    ProductPlatformSummary::class,
                    ProductTopCountries::class,
                    ProductLatestReviews::class,
                ])),
        ]);
    }

    public function getWidgetData(): array
    {
        return ['record' => $this->getRecord()];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('Uygulamaları düzenle')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->url(fn () => ProductResource::getUrl('edit', ['record' => $this->getRecord()])),
        ];
    }
}
