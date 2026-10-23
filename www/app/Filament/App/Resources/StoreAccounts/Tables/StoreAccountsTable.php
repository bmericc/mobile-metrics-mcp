<?php

namespace App\Filament\App\Resources\StoreAccounts\Tables;

use App\Actions\VerifyStoreAccount;
use App\Enums\Store;
use App\Filament\App\Resources\StoreAccounts\Schemas\StoreAccountForm;
use App\Jobs\SyncStoreAccount;
use App\Models\StoreAccount;
use App\Stores\GooglePlay\GoogleClient;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StoreAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('store')->label('Mağaza')->badge(),
                TextColumn::make('label')->label('Hesap')->searchable(),
                TextColumn::make('store_apps_count')->label('Uygulama')->counts('storeApps'),
                TextColumn::make('status')->label('Durum')->badge(),
                TextColumn::make('last_verified_at')->label('Son doğrulama')->dateTime()->sortable(),
                TextColumn::make('last_error')->label('Son hata')->limit(60)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('store')->options(Store::class),
            ])
            ->recordActions([
                Action::make('verify')
                    ->label('Doğrula')
                    ->icon('heroicon-o-check-badge')
                    ->action(function (StoreAccount $record) {
                        app(VerifyStoreAccount::class)($record)
                            ? Notification::make()->title('Kimlik bilgileri geçerli')->success()->send()
                            : Notification::make()->title('Doğrulama başarısız')->body($record->last_error)->danger()->send();
                    }),
                Action::make('sync')
                    ->label('Senkronize et')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (StoreAccount $record) => $record->status === 'active')
                    ->action(function (StoreAccount $record) {
                        SyncStoreAccount::dispatch($record);
                        Notification::make()->title('Senkronizasyon kuyruğa alındı')->success()->send();
                    }),
                Action::make('addPlayDeveloperAccount')
                    ->label('Geliştirici hesabı ekle')
                    ->icon('heroicon-o-plus')
                    ->visible(fn (StoreAccount $record) => $record->store === Store::GooglePlay && ($record->credentials['auth'] ?? null) === 'oauth')
                    ->modalHeading('Play geliştirici hesabı ekle')
                    ->modalDescription('Aynı Google bağlantısıyla erişilen başka bir Play Console geliştirici hesabını ayrı kayıt olarak ekler. Uygulamalar, bucket\'taki raporlara göre doğru hesaba taşınır.')
                    ->modalSubmitActionLabel('Ekle ve doğrula')
                    ->schema([
                        TextInput::make('label')
                            ->label('Geliştirici hesabı adı')
                            ->placeholder('ör. Tartı Medikal')
                            ->required(),
                        TextInput::make('reports_bucket')
                            ->label('Cloud Storage URI')
                            ->placeholder('gs://pubsite_prod_rev_01234567890123456789')
                            ->helperText(StoreAccountForm::bucketHelp())
                            ->required(),
                    ])
                    ->action(function (StoreAccount $record, array $data, VerifyStoreAccount $verify) {
                        $account = $record->workspace->storeAccounts()->create([
                            'store' => Store::GooglePlay,
                            'label' => $data['label'],
                            'credentials' => [
                                ...array_intersect_key($record->credentials, array_flip(['auth', 'email', 'refresh_token'])),
                                'reports_bucket' => GoogleClient::bucketName($data['reports_bucket']),
                            ],
                        ]);

                        if (! $verify($account)) {
                            Notification::make()->title('Hesap eklendi ama doğrulanamadı')->body($account->last_error)->danger()->persistent()->send();

                            return;
                        }

                        SyncStoreAccount::dispatch($account);
                        Notification::make()->title("{$account->label} eklendi, uygulamaları senkronize ediliyor")->success()->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
