<?php

namespace App\Filament\App\Resources\Products\Tables;

use App\Enums\Store;
use App\Models\Product;
use App\Models\StoreApp;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        $column = fn (Store $store) => TextColumn::make("apps_{$store->value}")
            ->label($store->label())
            ->state(fn (Product $record) => $record->storeApps
                ->filter(fn (StoreApp $app) => $app->store === $store)
                ->map(fn (StoreApp $app) => $app->name)
                ->values()
                ->all())
            ->badge()
            ->placeholder('—');

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('storeApps'))
            ->columns([
                TextColumn::make('name')
                    ->label('Ürün')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                $column(Store::GooglePlay),
                $column(Store::AppStore),
                $column(Store::AppGallery),
                TextColumn::make('updated_at')
                    ->label('Güncellendi')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->emptyStateHeading('Henüz ürün yok')
            ->emptyStateDescription('Aynı uygulamanın farklı mağazalardaki sürümlerini bir ürün altında toplayın; MCP ile ürün bazında soru sorabilirsiniz.')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
