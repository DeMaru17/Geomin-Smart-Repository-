<?php

namespace App\Filament\Resources\DocumentReviews\Tables;

use App\Models\Document;
use App\Services\DocumentReviewService;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentReviewsTable
{
    public static function table(Table $table): Table
    {
        $service = app(DocumentReviewService::class);

        return $table
            ->description('Ringkasan Jadwal Mendatang Riwayat Kaji Ulang')
            ->columns([
                TextColumn::make('document_number')
                    ->label('Nomor Dokumen')
                    ->searchable()
                    ->sortable()
                    ->color('primary')
                    ->weight('bold'),

                TextColumn::make('title')
                    ->label('Judul Dokumen')
                    ->searchable()
                    ->limit(50),

                TextColumn::make('baseline_date')
                    ->label('Tanggal Acuan')
                    ->state(fn(Document $record) => $service->getBaselineDate($record)->format('d-m-Y')),

                TextColumn::make('due_date')
                    ->label('Jatuh Tempo')
                    ->state(fn(Document $record) => $service->getDueDate($record)->format('d-m-Y')),

                TextColumn::make('sisa_hari')
                    ->label('Sisa Hari')
                    ->badge()
                    ->state(function (Document $record) use ($service): string {
                        $days = $service->getDaysRemaining($record);

                        if ($days < 0) {
                            return 'Terlewat ' . abs($days) . ' hari';
                        }

                        return $days . ' hari';
                    })
                    ->color(function (Document $record) use ($service): string {
                        $days = $service->getDaysRemaining($record);

                        if ($days < 0) {
                            return 'danger';
                        }

                        if ($days <= 60) {
                            return 'warning';
                        }

                        return 'success';
                    })
                    ->sortable(query: function ($query, string $direction) {
                        return $query->orderByRaw("
                            DATEDIFF(
                                DATE_ADD(
                                    GREATEST(
                                        COALESCE((SELECT MAX(dr.revision_date) FROM document_revisions dr WHERE dr.document_id = documents.id AND dr.status IN ('Published', 'Terbit')), documents.created_at),
                                        COALESCE((SELECT MAX(drev.review_date) FROM document_reviews drev WHERE drev.document_id = documents.id), documents.created_at)
                                    ),
                                    INTERVAL 365 DAY
                                ),
                                CURDATE()
                            ) {$direction}
                        ");
                    }),

                TextColumn::make('last_review_status')
                    ->label('Evaluasi Terakhir')
                    ->badge()
                    ->state(fn(Document $record) => $record->reviews->sortByDesc('review_date')->first()?->evaluation_status ?? '-')
                    ->color(fn(Document $record): ?string => match ($record->reviews->sortByDesc('review_date')->first()?->evaluation_status) {
                        'Dipertahankan' => 'success',
                        'Perlu direvisi' => 'warning',
                        'Perlu diperbaharui' => 'info',
                        'Perlu ditarik' => 'warning',
                        'Perlu dimusnahkan' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->emptyStateHeading('Tidak ada dokumen yang perlu dikaji ulang')
            ->emptyStateDescription('Semua dokumen masih dalam periode aman untuk kaji ulang.');
    }
}
