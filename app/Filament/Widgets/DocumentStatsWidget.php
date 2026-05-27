<?php

namespace App\Filament\Widgets;

use App\Services\DashboardStatsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DocumentStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getColumns(): int
    {
        return 2;
    }

    protected function getStats(): array
    {
        $service = app(DashboardStatsService::class);

        return [
            Stat::make('Total Dokumen Aktif', $service->getActiveDocumentCount()),
            Stat::make('Dokumen Internal', $service->getInternalDocumentCount()),
            Stat::make('Dokumen Eksternal', $service->getExternalDocumentCount()),
            Stat::make('Revisi Terbit', $service->getPublishedRevisionCount()),
        ];
    }
}
