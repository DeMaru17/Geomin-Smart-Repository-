<?php

namespace App\Filament\Resources\DocumentReviews\Widgets;

use App\Models\DocumentReview;
use App\Services\DocumentReviewService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DocumentReviewStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $service = app(DocumentReviewService::class);

        $overdueCount = $service->getOverdueDocuments()->count();
        $dueSoonCount = $service->getDueSoonDocuments()->count();
        $reviewedThisYear = DocumentReview::whereYear('review_date', now()->year)->count();

        return [
            Stat::make('Dokumen Overdue', $overdueCount)
                ->color('danger'),
            Stat::make('Jatuh Tempo < 60 Hari', $dueSoonCount)
                ->color('warning'),
            Stat::make('Kaji Ulang Tahun Ini', $reviewedThisYear)
                ->color('success'),
        ];
    }
}
