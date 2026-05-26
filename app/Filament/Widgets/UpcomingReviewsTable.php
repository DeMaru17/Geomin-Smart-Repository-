<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DocumentReviews\DocumentReviewResource;
use App\Models\Document;
use App\Services\DocumentReviewService;
use Carbon\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class UpcomingReviewsTable extends TableWidget
{
    protected static ?int $sort = 8;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'manajemen']);
    }

    public function table(Table $table): Table
    {
        $service = app(DocumentReviewService::class);

        return $table
            ->heading('Dokumen Mendekati Jatuh Tempo Kaji Ulang')
            ->query(
                Document::query()
                    ->whereHas('revisions', fn(Builder $q) => $q->whereIn('status', ['Published', 'Terbit']))
                    ->with(['revisions', 'reviews'])
                    ->orderByRaw("
                        DATEDIFF(
                            DATE_ADD(
                                GREATEST(
                                    COALESCE((SELECT MAX(dr.revision_date) FROM document_revisions dr WHERE dr.document_id = documents.id AND dr.status IN ('Published', 'Terbit')), documents.created_at),
                                    COALESCE((SELECT MAX(drev.review_date) FROM document_reviews drev WHERE drev.document_id = documents.id), documents.created_at)
                                ),
                                INTERVAL 365 DAY
                            ),
                            CURDATE()
                        ) ASC
                    ")
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('document_number')
                    ->label('Nomor Dokumen'),

                TextColumn::make('title')
                    ->label('Judul Dokumen')
                    ->limit(50),

                TextColumn::make('last_review_date')
                    ->label('Kaji Ulang Terakhir')
                    ->state(function (Document $record): string {
                        $lastReview = $record->reviews->sortByDesc('review_date')->first();

                        if (! $lastReview?->review_date) {
                            return '-';
                        }

                        return Carbon::parse($lastReview->review_date)->format('d-m-Y');
                    }),

                TextColumn::make('days_remaining')
                    ->label('Sisa Hari')
                    ->badge()
                    ->state(function (Document $record) use ($service): string {
                        $days = $service->getDaysRemaining($record);

                        if ($days < 0) {
                            return 'Overdue';
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
                    }),
            ])
            ->paginated(false)
            ->recordUrl(fn(Document $record): string => DocumentReviewResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Tidak ada data')
            ->emptyStateDescription('Tidak ada dokumen yang mendekati jadwal kaji ulang.');
    }
}
