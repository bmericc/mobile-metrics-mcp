<?php

namespace App\Filament\App\Resources\StoreAccounts;

use App\Filament\App\Resources\StoreAccounts\Pages\CreateStoreAccount;
use App\Filament\App\Resources\StoreAccounts\Pages\EditStoreAccount;
use App\Filament\App\Resources\StoreAccounts\Pages\ListStoreAccounts;
use App\Filament\App\Resources\StoreAccounts\Schemas\StoreAccountForm;
use App\Filament\App\Resources\StoreAccounts\Tables\StoreAccountsTable;
use App\Models\StoreAccount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StoreAccountResource extends Resource
{
    protected static ?string $model = StoreAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Schema $schema): Schema
    {
        return StoreAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StoreAccountsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStoreAccounts::route('/'),
            'create' => CreateStoreAccount::route('/create'),
            'edit' => EditStoreAccount::route('/{record}/edit'),
        ];
    }
}
