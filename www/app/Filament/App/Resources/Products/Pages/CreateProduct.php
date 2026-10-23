<?php

namespace App\Filament\App\Resources\Products\Pages;

use App\Filament\App\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    // \"Oluştur & yeni oluştur\" butonu gizlenir; kayıt sonrası listeye/düzenlemeye dönülür.
    protected static bool $canCreateAnother = false;
}
