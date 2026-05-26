<?php

namespace App\Filament\Widgets;

use App\Services\DashboardStatsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ChangeRequestDashboardStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $service = app(DashboardStatsService::class);
        $userId = $service->isAdminOrManajemen() ? null : auth()->id();

        return [
            Stat::make('Menunggu Persetujuan', $service->getPendingChangeRequestCount($userId))
                ->description('Pengajuan revisi pending'),
            Stat::make('Sedang Ditinjau', $service->getUnderReviewChangeRequestCount($userId))
                ->description('Pengajuan sedang dibahas'),
            Stat::make('Disetujui', $service->getApprovedChangeRequestCount($userId))
                ->description('Pengajuan disetujui'),
        ];
    }
}
