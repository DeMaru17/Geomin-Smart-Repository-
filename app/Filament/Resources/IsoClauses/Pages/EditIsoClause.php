<?php

namespace App\Filament\Resources\IsoClauses\Pages;

use App\Filament\Resources\IsoClauses\IsoClauseResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIsoClause extends EditRecord
{
    protected static string $resource = IsoClauseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
