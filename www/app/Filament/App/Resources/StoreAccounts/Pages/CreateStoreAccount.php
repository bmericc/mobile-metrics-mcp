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
