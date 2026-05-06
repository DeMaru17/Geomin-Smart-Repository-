<?php

namespace App\Filament\Resources\IsoClauses\Pages;

use App\Filament\Resources\IsoClauses\IsoClauseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIsoClauses extends ListRecords
{
    protected static string $resource = IsoClauseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
