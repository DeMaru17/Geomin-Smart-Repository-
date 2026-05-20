<?php

namespace App\Filament\Resources\ChangeRequests\Widgets;

use App\Models\ChangeRequest;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ChangeRequestStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Menunggu', ChangeRequest::where('approval_status', 'Pending')->count()),
            Stat::make('Ditinjau', ChangeRequest::whereIn('approval_status', ['Di uji coba', 'Dibahas di RTM'])->count()),
            Stat::make('Disetujui', ChangeRequest::where('approval_status', 'Segera dibuat revisi')->count()),
            Stat::make('Ditolak', ChangeRequest::where('approval_status', 'Ditolak')->count()),
        ];
    }
}
