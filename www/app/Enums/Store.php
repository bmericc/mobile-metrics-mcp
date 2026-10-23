<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Store: string implements HasLabel
{
    case GooglePlay = 'google_play';
    case AppStore = 'app_store';
    case AppGallery = 'app_gallery';

    public function label(): string
    {
        return match ($this) {
            self::GooglePlay => 'Google Play',
            self::AppStore => 'App Store',
            self::AppGallery => 'AppGallery',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
