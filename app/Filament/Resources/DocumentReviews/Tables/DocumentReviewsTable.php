<?php

namespace App\Filament\Resources\DocumentReviews\Tables;

use App\Models\Document;
use App\Models\DocumentReview;
use App\Services\DocumentReviewService;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_review')
                        ->label('Catat Kaji Ulang Masal')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->color('primary')
                        ->visible(fn (): bool => in_array(auth()->user()?->role, ['admin', 'manajemen']))
                        ->form([
                            DatePicker::make('review_date')
                                ->label('Tanggal Kaji Ulang')
                                ->required()
                                ->default(now()),

                            Select::make('evaluation_status')
                                ->label('Status Evaluasi')
                                ->required()
                                ->default('Dipertahankan')
                                ->options([
                                    'Dipertahankan' => 'Dipertahankan',
                                    'Perlu direvisi' => 'Perlu direvisi',
                                    'Perlu diperbaharui' => 'Perlu diperbaharui',
                                    'Perlu ditarik' => 'Perlu ditarik',
                                    'Perlu dimusnahkan' => 'Perlu dimusnahkan',
                                ])
                                ->live(),

                            Select::make('destruction_method')
                                ->label('Metode Pemusnahan / Penarikan')
                                ->options([
                                    'Penarikan Akses Digital' => 'Penarikan Akses Digital',
                                    'Penghancuran Dokumen Fisik/Cetak' => 'Penghancuran Dokumen Fisik/Cetak',
                                    'Lainnya' => 'Lainnya',
                                ])
                                ->default('Penarikan Akses Digital')
                                ->visible(fn ($get): bool => $get('evaluation_status') === 'Perlu dimusnahkan')
                                ->required(fn ($get): bool => $get('evaluation_status') === 'Perlu dimusnahkan'),

                            Textarea::make('remarks')
                                ->label('Catatan')
                                ->placeholder('Masukkan catatan kaji ulang untuk dokumen-dokumen yang dipilih...')
                                ->maxLength(1000),
                        ])
                        ->modalHeading('Catat Kaji Ulang Masal')
                        ->modalDescription('Catat hasil evaluasi kaji ulang untuk seluruh dokumen yang dipilih.')
                        ->modalSubmitActionLabel('Simpan Kaji Ulang')
                        ->action(function (Collection $records, array $data) use ($service): void {
                            $destructionCount = 0;

                            DB::transaction(function () use ($records, $data, $service, &$destructionCount) {
                                foreach ($records as $record) {
                                    DocumentReview::create([
                                        'document_id' => $record->id,
                                        'review_date' => $data['review_date'],
                                        'reviewer_id' => auth()->id(),
                                        'evaluation_status' => $data['evaluation_status'],
                                        'remarks' => $data['remarks'] ?? null,
                                    ]);

                                    if ($data['evaluation_status'] === 'Perlu dimusnahkan') {
                                        $destruction = $service->createDestructionRequestIfNeeded(
                                            document: $record,
                                            proposerId: auth()->id(),
                                            reason: $data['remarks'] ?? null,
                                            method: $data['destruction_method'] ?? 'Penarikan Akses Digital'
                                        );

                                        if ($destruction && $destruction->wasRecentlyCreated) {
                                            $destructionCount++;
                                        }
                                    }
                                }
                            });

                            $body = "{$records->count()} dokumen berhasil dikaji ulang dengan status '{$data['evaluation_status']}'.";
                            if ($destructionCount > 0) {
                                $body .= " Sebanyak {$destructionCount} pengajuan pemusnahan dokumen otomatis dibuat (Pending).";
                            }

                            Notification::make()
                                ->title('Kaji ulang masal berhasil dicatat')
                                ->body($body)
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('Tidak ada dokumen yang perlu dikaji ulang')
            ->emptyStateDescription('Semua dokumen masih dalam periode aman untuk kaji ulang.');
    }
}
