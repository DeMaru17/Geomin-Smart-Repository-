<?php

namespace App\Filament\Resources\DistributionLogs\Pages;

use App\Filament\Resources\DistributionLogs\DistributionLogResource;
use App\Filament\Resources\DistributionLogs\Exports\DistributionLogExport;
use Filament\Resources\Pages\ListRecords;
use pxlrbt\FilamentExcel\Actions\ExportAction;

class ListDistributionLogs extends ListRecords
{
    protected static string $resource = DistributionLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->label('Export Excel')
                ->exports([
                    DistributionLogExport::make(),
                ]),
        ];
    }
}
