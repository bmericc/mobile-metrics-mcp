<?php

namespace App\Stores;

use App\Enums\Store;
use App\Stores\AppGallery\AppGalleryCollector;
use App\Stores\AppStore\AppStoreCollector;
use App\Stores\Contracts\StoreCollector;
use App\Stores\GooglePlay\GooglePlayCollector;

class StoreRegistry
{
    public function for(Store $store): StoreCollector
    {
        return app(match ($store) {
            Store::GooglePlay => GooglePlayCollector::class,
            Store::AppStore => AppStoreCollector::class,
            Store::AppGallery => AppGalleryCollector::class,
        });
    }
}
