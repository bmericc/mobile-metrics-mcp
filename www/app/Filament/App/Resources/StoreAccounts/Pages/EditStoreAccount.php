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

    /**
     * Gizli olmayan alanlar forma geri basılır; anahtarlar basılmaz.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['credentials'] = array_intersect_key(
            $this->record->credentials ?? [],
            array_flip(['reports_bucket', 'issuer_id', 'key_id', 'vendor_number', 'client_id', 'package_names', 'review_site', 'review_countries']),
        );

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['credentials'] = [
            ...$this->record->credentials,
            ...array_filter($data['credentials'] ?? [], fn ($value) => filled($value)),
            // Bucket bilinçli olarak boşaltılabilmeli (opsiyonel alan).
            'reports_bucket' => $data['credentials']['reports_bucket'] ?? null,
        ];

        return $data;
    }

    protected function afterSave(): void
    {
        app(VerifyStoreAccount::class)($this->record);
    }
}
