<?php

namespace App\Filament\App\Resources\StoreApps;

use App\Filament\App\Resources\StoreApps\Pages\CreateStoreApp;
use App\Filament\App\Resources\StoreApps\Pages\EditStoreApp;
use App\Filament\App\Resources\StoreApps\Pages\ListStoreApps;
use App\Filament\App\Resources\StoreApps\Schemas\StoreAppForm;
use App\Filament\App\Resources\StoreApps\Tables\StoreAppsTable;
use App\Models\StoreApp;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StoreAppResource extends Resource
{
    protected static ?string $model = StoreApp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return StoreAppForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StoreAppsTable::configure($table);
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
            'index' => ListStoreApps::route('/'),
            'create' => CreateStoreApp::route('/create'),
            'edit' => EditStoreApp::route('/{record}/edit'),
        ];
    }
}
