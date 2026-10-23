<?php

namespace App\Filament\App\Resources\StoreAccounts\Pages;

use App\Actions\VerifyStoreAccount;
use App\Filament\App\Resources\StoreAccounts\StoreAccountResource;
use App\Jobs\SyncStoreAccount;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateStoreAccount extends CreateRecord
{
    protected static string $resource = StoreAccountResource::class;

    // \"Oluştur & yeni oluştur\" butonu gizlenir; kayıt sonrası listeye/düzenlemeye dönülür.
    protected static bool $canCreateAnother = false;

    protected function afterCreate(): void
    {
        if (app(VerifyStoreAccount::class)($this->record)) {
            SyncStoreAccount::dispatch($this->record);
            Notification::make()->title('Hesap doğrulandı, ilk senkronizasyon başladı')->success()->send();

            return;
        }

        Notification::make()
            ->title('Hesap kaydedildi ama doğrulanamadı')
            ->body($this->record->last_error)
            ->danger()
            ->persistent()
            ->send();
    }
}
