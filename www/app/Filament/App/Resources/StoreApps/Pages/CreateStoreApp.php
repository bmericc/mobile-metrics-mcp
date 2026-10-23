<?php

namespace App\Filament\App\Resources\StoreApps\Pages;

use App\Filament\App\Resources\StoreApps\StoreAppResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStoreApp extends CreateRecord
{
    protected static string $resource = StoreAppResource::class;

    // \"Oluştur & yeni oluştur\" butonu gizlenir; kayıt sonrası listeye/düzenlemeye dönülür.
    protected static bool $canCreateAnother = false;
}
