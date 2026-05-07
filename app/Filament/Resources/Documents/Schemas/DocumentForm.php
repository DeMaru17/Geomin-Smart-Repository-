<?php

namespace App\Filament\Resources\Documents\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('document_number')
                    ->required(),
                TextInput::make('title')
                    ->required(),
                TextInput::make('category_id')
                    ->required()
                    ->numeric(),
                TextInput::make('department_id')
                    ->required()
                    ->numeric(),
                Toggle::make('is_external')
                    ->required(),
                TextInput::make('retention_period_months')
                    ->required()
                    ->numeric()
                    ->default(36),
            ]);
    }
}
