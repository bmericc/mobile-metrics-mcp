<?php

namespace App\Filament\App\Resources\Products\Schemas;

use App\Enums\Store;
use App\Models\Product;
use App\Models\StoreApp;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Ürün = aynı uygulamanın mağazalardaki karşılıkları. Uygulamalar store_apps.product_id ile bağlanır;
 * form bunu "platform + uygulama" satırlarıyla düzenler ve kayıtta product_id'leri senkronize eder.
 */
class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Ürün adı')
                    ->placeholder('ör. Radyo C')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Section::make('Platformlardaki uygulamalar')
                    ->description('Bu ürünün Google Play, App Store ve AppGallery\'deki karşılıklarını seçin. Başka bir ürüne bağlı bir uygulamayı seçerseniz bu ürüne taşınır.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('apps')
                            ->hiddenLabel()
                            ->schema([
                                Select::make('store')
                                    ->label('Platform')
                                    ->options(Store::class)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('store_app_id', null)),
                                Select::make('store_app_id')
                                    ->label('Uygulama')
                                    ->options(fn (Get $get, ?Product $record) => self::appOptions($get('store'), $record))
                                    ->searchable()
                                    ->required()
                                    ->disabled(fn (Get $get) => blank($get('store')))
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Uygulama ekle')
                            ->reorderable(false)
                            ->afterStateHydrated(function (Repeater $component, ?Product $record) {
                                $component->state(
                                    $record?->storeApps()->orderBy('store')->get()
                                        ->map(fn (StoreApp $app) => ['store' => $app->store->value, 'store_app_id' => $app->id])
                                        ->all() ?? []
                                );
                            })
                            ->dehydrated(false)
                            ->saveRelationshipsUsing(fn (Product $record, ?array $state) => self::syncApps($record, $state ?? [])),
                    ]),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function appOptions(mixed $store, ?Product $record): array
    {
        $store = $store instanceof Store ? $store->value : $store;

        if (blank($store)) {
            return [];
        }

        return StoreApp::query()
            ->with('product')
            ->where('workspace_id', Filament::getTenant()->getKey())
            ->where('store', $store)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (StoreApp $app) use ($record) {
                $label = "{$app->name} ({$app->external_id})";

                if ($app->product && $app->product_id !== $record?->id) {
                    $label .= " — şu an: {$app->product->name}";
                }

                return [$app->id => $label];
            })
            ->all();
    }

    /**
     * @param  array<array{store?: string, store_app_id?: int|string|null}>  $state
     */
    private static function syncApps(Product $product, array $state): void
    {
        $ids = collect($state)->pluck('store_app_id')->filter()->map(fn ($id) => (int) $id)->unique();

        // Yalnızca aynı workspace'teki uygulamalar bağlanabilir.
        $scope = StoreApp::query()->where('workspace_id', $product->workspace_id);

        (clone $scope)->where('product_id', $product->id)->whereNotIn('id', $ids)->update(['product_id' => null]);
        (clone $scope)->whereIn('id', $ids)->update(['product_id' => $product->id]);
    }
}
