<?php

namespace App\Filament\App\Resources\StoreAccounts\Pages;

use App\Filament\App\Resources\StoreAccounts\StoreAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStoreAccounts extends ListRecords
{
    protected static string $resource = StoreAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
