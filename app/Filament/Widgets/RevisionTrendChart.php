<?php

namespace App\Filament\Widgets;

use App\Services\DashboardStatsService;
use Filament\Widgets\ChartWidget;

class RevisionTrendChart extends ChartWidget
{
    protected ?string $heading = 'Tren Revisi Bulanan';

    protected static ?int $sort = 7;

    protected int | string | array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $service = app(DashboardStatsService::class);
        $trend = $service->getMonthlyRevisionTrend(12);

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Revisi',
                    'data' => array_column($trend, 'count'),
                    'borderColor' => '#3b82f6',
                    'fill' => false,
                ],
            ],
            'labels' => array_column($trend, 'month'),
        ];
    }
}
