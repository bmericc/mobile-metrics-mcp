<?php

namespace App\Filament\Resources\SyncRuns\Schemas;

use App\Enums\SyncStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SyncRunForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('store_account_id')
                    ->relationship('storeAccount', 'id')
                    ->required(),
                Select::make('store_app_id')
                    ->relationship('storeApp', 'name'),
                TextInput::make('job')
                    ->required(),
                Select::make('status')
                    ->options(SyncStatus::class)
                    ->required(),
                TextInput::make('rows')
                    ->required()
                    ->numeric()
                    ->default(0),
                Textarea::make('error')
                    ->columnSpanFull(),
                DateTimePicker::make('started_at')
                    ->required(),
                DateTimePicker::make('finished_at'),
            ]);
    }
}
