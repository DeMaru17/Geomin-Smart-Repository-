<?php

namespace App\Filament\Widgets;

use App\Services\DashboardStatsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class QualityRecordAlertWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'manajemen']);
    }

    protected function getStats(): array
    {
        $service = app(DashboardStatsService::class);

        $expiredCount = $service->getExpiredQualityRecordCount();
        $nearExpirationCount = $service->getNearExpirationQualityRecordCount(30);

        $expiredStat = Stat::make('Rekaman Mutu Kedaluwarsa', $expiredCount);
        if ($expiredCount > 0) {
            $expiredStat->color('danger');
        }

        $nearExpirationStat = Stat::make('Mendekati Kedaluwarsa', $nearExpirationCount);
        if ($nearExpirationCount > 0) {
            $nearExpirationStat->color('warning');
        }

        return [
            $expiredStat,
            $nearExpirationStat,
        ];
    }
}
