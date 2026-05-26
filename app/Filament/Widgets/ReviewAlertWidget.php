<?php

namespace App\Filament\Widgets;

use App\Models\DocumentReview;
use App\Services\DocumentReviewService;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReviewAlertWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'manajemen']);
    }

    protected function getStats(): array
    {
        $service = app(DocumentReviewService::class);

        $overdueCount = $service->getOverdueDocuments()->count();
        $dueSoonCount = $service->getDueSoonDocuments()->count();
        $completedThisYear = DocumentReview::whereYear('review_date', Carbon::now()->year)->count();

        $overdueStat = Stat::make('Dokumen Overdue', $overdueCount);
        if ($overdueCount > 0) {
            $overdueStat = $overdueStat->color('danger');
        }

        $dueSoonStat = Stat::make('Jatuh Tempo 60 Hari', $dueSoonCount);
        if ($dueSoonCount > 0) {
            $dueSoonStat = $dueSoonStat->color('warning');
        }

        return [
            $overdueStat,
            $dueSoonStat,
            Stat::make('Kaji Ulang Tahun Ini', $completedThisYear),
        ];
    }
}
