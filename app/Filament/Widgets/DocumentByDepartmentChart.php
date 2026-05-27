<?php

namespace App\Filament\Widgets;

use App\Services\DashboardStatsService;
use Filament\Widgets\ChartWidget;

class DocumentByDepartmentChart extends ChartWidget
{
    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    protected ?string $heading = 'Dokumen per Departemen';

    protected ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $service = app(DashboardStatsService::class);
        $data = $service->getDocumentsByDepartment();

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Dokumen',
                    'data' => array_column($data, 'count'),
                    'backgroundColor' => '#36A2EB',
                ],
            ],
            'labels' => array_column($data, 'name'),
        ];
    }
}
