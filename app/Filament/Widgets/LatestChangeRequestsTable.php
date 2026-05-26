<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ChangeRequests\ChangeRequestResource;
use App\Models\ChangeRequest;
use App\Services\DashboardStatsService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class LatestChangeRequestsTable extends TableWidget
{
    protected static ?int $sort = 9;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $service = app(DashboardStatsService::class);
        $userId = $service->isAdminOrManajemen() ? null : Auth::id();

        return $table
            ->query(
                ChangeRequest::query()
                    ->with(['document', 'proposer'])
                    ->when($userId !== null, fn(Builder $query) => $query->where('proposer_id', $userId))
            )
            ->heading('Pengajuan Revisi Terbaru')
            ->columns([
                TextColumn::make('document.title')
                    ->label('Judul Dokumen')
                    ->limit(40),
                TextColumn::make('proposer.name')
                    ->label('Pengusul'),
                TextColumn::make('proposal_date')
                    ->label('Tanggal Pengajuan')
                    ->date('d-m-Y')
                    ->sortable(),
                TextColumn::make('approval_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Pending' => 'warning',
                        'Di uji coba', 'Dibahas di RTM' => 'info',
                        'Segera dibuat revisi' => 'success',
                        'Ditolak' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('proposal_date', 'desc')
            ->defaultPaginationPageOption(5)
            ->recordUrl(fn(ChangeRequest $record): string => ChangeRequestResource::getUrl('edit', ['record' => $record]))
            ->emptyStateHeading('Tidak ada data')
            ->emptyStateDescription('Belum ada pengajuan revisi.');
    }
}
