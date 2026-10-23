<?php

namespace App\Filament\App\Resources\StoreApps\Schemas;

use App\Models\StoreAccount;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class StoreAppForm
{
    public static function configure(Schema $schema): Schema
    {
        // Uygulamalar normalde sync ile keşfedilir; elle ekleme keşfin desteklenmediği durumlar için.
        return $schema
            ->components([
                Select::make('store_account_id')
                    ->label('Mağaza hesabı')
                    ->relationship('storeAccount', 'label')
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('store', StoreAccount::find($state)?->store?->value))
                    ->disabledOn('edit'),
                TextInput::make('store')->hidden()->dehydratedWhenHidden(),
                Select::make('product_id')
                    ->label('Ürün')
                    ->relationship('product', 'name'),
                TextInput::make('external_id')
                    ->label('Mağaza ID (package / app id)')
                    ->required()
                    ->disabledOn('edit'),
                TextInput::make('name')->label('Ad')->required(),
                TextInput::make('icon_url')->url(),
                Toggle::make('is_active')->label('Senkronize et')->default(true),
            ]);
    }
}
