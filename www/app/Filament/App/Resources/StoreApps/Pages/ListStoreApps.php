<?php

namespace App\Filament\App\Resources\StoreApps\Pages;

use App\Filament\App\Resources\StoreApps\StoreAppResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStoreApps extends ListRecords
{
    protected static string $resource = StoreAppResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
