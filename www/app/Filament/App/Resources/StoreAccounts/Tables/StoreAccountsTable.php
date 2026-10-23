<?php

namespace App\Filament\App\Resources\StoreAccounts\Tables;

use App\Enums\Store;
use App\Jobs\SyncStoreAccount;
use App\Models\StoreAccount;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
                Action::make('sync')
                    ->label('Senkronize et')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (StoreAccount $record) {
                        SyncStoreAccount::dispatch($record);
                        Notification::make()->title('Senkronizasyon kuyruğa alındı')->success()->send();
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
