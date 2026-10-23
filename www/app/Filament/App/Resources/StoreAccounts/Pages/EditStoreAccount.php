<?php

namespace App\Filament\App\Resources\StoreAccounts\Pages;

use App\Actions\VerifyStoreAccount;
use App\Filament\App\Resources\StoreAccounts\StoreAccountResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStoreAccount extends EditRecord
{
    protected static string $resource = StoreAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['credentials'] = [
            ...$this->record->credentials,
            ...array_filter($data['credentials'] ?? [], fn ($value) => filled($value)),
        ];

        return $data;
    }

    protected function afterSave(): void
    {
        app(VerifyStoreAccount::class)($this->record);
    }
}
