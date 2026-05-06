<?php

namespace App\Filament\Resources\DocumentCategories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DocumentCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('name')
                    ->required(),
            ]);
    }
}
