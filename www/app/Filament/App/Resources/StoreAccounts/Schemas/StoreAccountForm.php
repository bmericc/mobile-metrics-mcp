<?php

namespace App\Filament\App\Resources\StoreAccounts\Schemas;

use App\Enums\Store;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class StoreAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        // Kayıtlı kimlik bilgileri forma geri basılmaz; düzenlemede boş bırakılan alan
        // mevcut değeri korur (bkz. EditStoreAccount::mutateFormDataBeforeSave).
        $requiredOnCreate = fn (string $operation) => $operation === 'create';

        return $schema
            ->components([
                Select::make('store')
                    ->label('Mağaza')
                    ->options(Store::class)
                    ->required()
                    ->live()
                    ->disabledOn('edit'),
                TextInput::make('label')
                    ->label('Hesap adı')
                    ->required(),

                Section::make('Google Play kimlik bilgileri')
                    ->visible(fn (Get $get) => self::isStore($get('store'), Store::GooglePlay))
                    ->schema([
                        Textarea::make('credentials.service_account_json')
                            ->label('Service account JSON')
                            ->rows(6)
                            ->required($requiredOnCreate),
                        TextInput::make('credentials.reports_bucket')
                            ->label('Rapor bucket')
                            ->placeholder('pubsite_prod_rev_0123456789')
                            ->required($requiredOnCreate),
                    ]),

                Section::make('App Store Connect kimlik bilgileri')
                    ->visible(fn (Get $get) => self::isStore($get('store'), Store::AppStore))
                    ->schema([
                        TextInput::make('credentials.issuer_id')->label('Issuer ID')->required($requiredOnCreate),
                        TextInput::make('credentials.key_id')->label('Key ID')->required($requiredOnCreate),
                        Textarea::make('credentials.private_key')
                            ->label('.p8 anahtar içeriği')
                            ->rows(6)
                            ->required($requiredOnCreate),
                        TextInput::make('credentials.vendor_number')->label('Vendor number (satış raporları)'),
                    ]),

                Section::make('AppGallery Connect kimlik bilgileri')
                    ->visible(fn (Get $get) => self::isStore($get('store'), Store::AppGallery))
                    ->schema([
                        TextInput::make('credentials.client_id')->label('Client ID')->required($requiredOnCreate),
                        TextInput::make('credentials.client_secret')
                            ->label('Client secret')
                            ->password()
                            ->required($requiredOnCreate),
                    ]),
            ]);
    }

    private static function isStore(mixed $value, Store $store): bool
    {
        return ($value instanceof Store ? $value : Store::tryFrom((string) $value)) === $store;
    }
}
