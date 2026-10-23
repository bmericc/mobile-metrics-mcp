<?php

namespace App\Filament\App\Resources\Products\Pages;

use App\Filament\App\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('report')
                ->label('Rapor')
                ->icon('heroicon-o-chart-bar')
                ->url(fn () => ProductResource::getUrl('report', ['record' => $this->getRecord()])),
            DeleteAction::make(),
        ];
    }
}
