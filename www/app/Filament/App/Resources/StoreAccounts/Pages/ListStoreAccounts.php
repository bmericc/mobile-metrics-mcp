<?php

namespace App\Filament\App\Resources\StoreAccounts\Pages;

use App\Filament\App\Resources\StoreAccounts\Actions\AddStoreAccountAction;
use App\Filament\App\Resources\StoreAccounts\StoreAccountResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListStoreAccounts extends ListRecords
{
    protected static string $resource = StoreAccountResource::class;

    public function mount(): void
    {
        parent::mount();

        // StoreConnectController OAuth dönüşünde sonucu flash ile bırakır.
        if ($label = session('store_connect.success')) {
            Notification::make()->title("{$label} bağlandı, ilk senkronizasyon başladı")->success()->send();
        }

        if ($error = session('store_connect.error')) {
            Notification::make()->title('Hesap bağlandı ama doğrulanamadı')->body($error)->danger()->persistent()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('connectGooglePlay')
                ->label('Google Play hesabı bağla')
                ->icon('heroicon-o-link')
                ->url(fn () => route('store.connect', ['google_play', Filament::getTenant()])),
            AddStoreAccountAction::appStore(),
            AddStoreAccountAction::appGallery(),
            CreateAction::make()
                ->label('Gelişmiş: anahtarla ekle')
                ->color('gray'),
        ];
    }
}
