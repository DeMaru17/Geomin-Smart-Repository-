<?php

namespace App\Filament\Resources\IsoClauses\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class IsoClauseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('clause_number')
                    ->required(),
                TextInput::make('description')
                    ->required(),
            ]);
    }
}
