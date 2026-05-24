<?php

namespace App\Filament\Resources\DocumentDestructions\Pages;

use App\Filament\Resources\DocumentDestructions\DocumentDestructionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDocumentDestructions extends ListRecords
{
    protected static string $resource = DocumentDestructionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat Pengajuan'),
        ];
    }
}
