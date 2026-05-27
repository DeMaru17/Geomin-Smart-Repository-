<?php

namespace App\Filament\Widgets;

use App\Services\DashboardStatsService;
use Filament\Widgets\ChartWidget;

class DocumentByCategoryChart extends ChartWidget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    protected ?string $heading = 'Dokumen per Kategori';

    protected ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $service = app(DashboardStatsService::class);
        $categories = $service->getDocumentsByCategory();

        $labels = array_column($categories, 'name');
        $counts = array_column($categories, 'count');
        $colors = $this->generateColors(count($categories));

        return [
            'datasets' => [
                [
                    'data' => $counts,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * Generate distinct colors for chart segments using a predefined palette.
     *
     * @return array<int, string>
     */
    private function generateColors(int $count): array
    {
        $palette = [
            '#36A2EB', // blue
            '#FF6384', // red/pink
            '#FFCE56', // yellow
            '#4BC0C0', // teal
            '#9966FF', // purple
            '#FF9F40', // orange
            '#C9CBCF', // gray
            '#7BC8A4', // green
            '#E7E9ED', // light gray
            '#8B5CF6', // violet
            '#EC4899', // magenta
            '#14B8A6', // cyan
            '#F97316', // dark orange
            '#84CC16', // lime
            '#06B6D4', // sky blue
        ];

        $colors = [];

        for ($i = 0; $i < $count; $i++) {
            $colors[] = $palette[$i % count($palette)];
        }

        return $colors;
    }
}
