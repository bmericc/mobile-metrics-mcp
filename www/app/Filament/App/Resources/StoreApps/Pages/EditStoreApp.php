<?php

namespace App\Filament\App\Resources\StoreApps\Pages;

use App\Filament\App\Resources\StoreApps\StoreAppResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStoreApp extends EditRecord
{
    protected static string $resource = StoreAppResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
