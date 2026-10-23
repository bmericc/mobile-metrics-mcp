<?php

namespace App\Filament\App\Pages;

use App\Models\Workspace;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class RegisterWorkspace extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Yeni workspace';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Ad')->required()->maxLength(255),
        ]);
    }

    protected function handleRegistration(array $data): Model
    {
        $workspace = Workspace::create([...$data, 'owner_id' => auth()->id()]);
        $workspace->users()->attach(auth()->id(), ['role' => 'owner']);

        return $workspace;
    }
}
