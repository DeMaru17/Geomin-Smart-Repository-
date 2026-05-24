<?php

namespace App\Filament\Resources\DocumentReviews\Infolists;

use App\Filament\Resources\DocumentDestructions\DocumentDestructionResource;
use App\Models\ChangeRequest;
use App\Models\Document;
use App\Services\DocumentReviewService;
use Filament\Actions\Action as InfolistAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class DocumentReviewInfolist
{
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('Tabs')
                    ->columnSpanFull()
                    ->tabs([
                        self::tabRingkasan(),
                        self::tabRiwayatKajiUlang(),
                    ]),
            ]);
    }

    private static function tabRingkasan(): Tab
    {
        return Tab::make('Ringkasan')
            ->schema([
                Section::make('Informasi Dokumen')
                    ->icon('heroicon-m-document-text')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('document_number')
                                ->label('Nomor Dokumen'),
                            TextEntry::make('title')
                                ->label('Judul Dokumen'),
                            TextEntry::make('category.name')
                                ->label('Kategori'),
                            TextEntry::make('department.name')
                                ->label('Departemen'),
                            TextEntry::make('latest_published_revision_number')
                                ->label('Nomor Revisi Terakhir')
                                ->state(function (Document $record): string {
                                    $latestPublished = $record->revisions
                                        ->whereIn('status', ['Published', 'Terbit'])
                                        ->sortByDesc('revision_date')
                                        ->first();

                                    return $latestPublished
                                        ? 'Rev. ' . str_pad($latestPublished->revision_number, 2, '0', STR_PAD_LEFT)
                                        : '-';
                                }),
                            TextEntry::make('latest_published_revision_date')
                                ->label('Tanggal Revisi Terakhir')
                                ->state(function (Document $record): string {
                                    $latestPublished = $record->revisions
                                        ->whereIn('status', ['Published', 'Terbit'])
                                        ->sortByDesc('revision_date')
                                        ->first();

                                    return $latestPublished && $latestPublished->revision_date
                                        ? $latestPublished->revision_date->format('d-m-Y')
                                        : '-';
                                }),
                        ]),
                    ]),

                Section::make('Status Kaji Ulang')
                    ->icon('heroicon-m-calendar')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('baseline_date')
                                ->label('Baseline Date')
                                ->state(function (Document $record): string {
                                    $service = app(DocumentReviewService::class);

                                    return $service->getBaselineDate($record)->format('d-m-Y');
                                }),
                            TextEntry::make('due_date')
                                ->label('Due Date')
                                ->state(function (Document $record): string {
                                    $service = app(DocumentReviewService::class);

                                    return $service->getDueDate($record)->format('d-m-Y');
                                }),
                            TextEntry::make('sisa_hari')
                                ->label('Sisa Hari')
                                ->badge()
                                ->state(function (Document $record): string {
                                    $service = app(DocumentReviewService::class);
                                    $days = $service->getDaysRemaining($record);

                                    if ($days < 0) {
                                        return 'Terlewat ' . abs($days) . ' hari';
                                    }

                                    return $days . ' hari';
                                })
                                ->color(function (Document $record): string {
                                    $service = app(DocumentReviewService::class);
                                    $days = $service->getDaysRemaining($record);

                                    if ($days < 0) {
                                        return 'danger';
                                    }

                                    if ($days <= 60) {
                                        return 'warning';
                                    }

                                    return 'success';
                                }),
                            TextEntry::make('tanggal_evaluasi_terakhir')
                                ->label('Tanggal Evaluasi Terakhir')
                                ->state(function (Document $record): string {
                                    $latestReview = $record->reviews
                                        ->sortByDesc('review_date')
                                        ->first();

                                    return $latestReview && $latestReview->review_date
                                        ? $latestReview->review_date->format('d-m-Y')
                                        : 'Belum pernah dikaji';
                                }),
                            TextEntry::make('status_evaluasi_terakhir')
                                ->label('Status Evaluasi Terakhir')
                                ->badge()
                                ->state(function (Document $record): ?string {
                                    $latestReview = $record->reviews
                                        ->sortByDesc('review_date')
                                        ->first();

                                    return $latestReview?->evaluation_status;
                                })
                                ->color(function (Document $record): ?string {
                                    $latestReview = $record->reviews
                                        ->sortByDesc('review_date')
                                        ->first();

                                    if (! $latestReview) {
                                        return null;
                                    }

                                    return self::getEvaluationStatusColor($latestReview->evaluation_status);
                                }),
                        ]),
                    ]),
            ]);
    }

    private static function tabRiwayatKajiUlang(): Tab
    {
        return Tab::make('Riwayat Kaji Ulang')
            ->schema([
                RepeatableEntry::make('reviews')
                    ->hiddenLabel()
                    ->schema([
                        TextEntry::make('review_date')
                            ->label('Tanggal Kaji Ulang')
                            ->date('d-m-Y'),
                        TextEntry::make('reviewer.name')
                            ->label('Reviewer')
                            ->default('-'),
                        TextEntry::make('evaluation_status')
                            ->label('Status Evaluasi')
                            ->badge()
                            ->color(fn(?string $state): ?string => self::getEvaluationStatusColor($state)),
                        TextEntry::make('remarks')
                            ->label('Catatan')
                            ->default('-'),
                        Actions::make([
                            self::buatPengajuanRevisiAction(),
                            self::buatBaPemusnahanAction(),
                        ]),
                    ])
                    ->columns(5)
                    ->placeholder('Belum ada data kaji ulang untuk dokumen ini'),
            ]);
    }

    private static function buatPengajuanRevisiAction(): InfolistAction
    {
        return InfolistAction::make('buat_pengajuan_revisi')
            ->label('Buat Pengajuan Revisi')
            ->icon('heroicon-m-pencil-square')
            ->color('warning')
            ->visible(fn($record) => $record->evaluation_status === 'Perlu direvisi')
            ->form([
                Select::make('document_id')
                    ->label('Dokumen')
                    ->required()
                    ->options(function ($record) {
                        $document = $record->document;

                        return [$document->id => "{$document->document_number} - {$document->title}"];
                    })
                    ->default(fn($record) => $record->document_id)
                    ->disabled()
                    ->dehydrated(),
                DatePicker::make('proposal_date')
                    ->label('Tanggal Pengajuan')
                    ->required()
                    ->default(now()),
                Textarea::make('reason')
                    ->label('Alasan Perubahan')
                    ->required()
                    ->default(fn($record) => $record->remarks)
                    ->maxLength(1000),
                Repeater::make('proposed_changes')
                    ->label('Detail Perubahan')
                    ->minItems(1)
                    ->maxItems(50)
                    ->schema([
                        TextInput::make('halaman')
                            ->label('Halaman')
                            ->required()
                            ->maxLength(50),
                        TextInput::make('item')
                            ->label('Item')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('detail_usulan')
                            ->label('Detail Usulan')
                            ->required()
                            ->maxLength(1000),
                    ]),
            ])
            ->modalHeading('Buat Pengajuan Revisi')
            ->action(function ($record, array $data) {
                ChangeRequest::create([
                    'document_id' => $record->document_id,
                    'proposer_id' => auth()->id(),
                    'proposal_date' => $data['proposal_date'],
                    'reason' => $data['reason'],
                    'proposed_changes' => $data['proposed_changes'],
                    'approval_status' => 'Pending',
                ]);

                Notification::make()
                    ->title('Pengajuan Revisi Berhasil')
                    ->body('Pengajuan revisi dokumen telah berhasil dibuat.')
                    ->success()
                    ->send();
            });
    }

    private static function buatBaPemusnahanAction(): InfolistAction
    {
        return InfolistAction::make('buat_ba_pemusnahan')
            ->label('Buat BA Pemusnahan')
            ->icon('heroicon-m-trash')
            ->color('danger')
            ->visible(fn($record) => $record->evaluation_status === 'Perlu dimusnahkan')
            ->url(fn($record) => DocumentDestructionResource::getUrl('create', [
                'document_id' => $record->document_id,
                'reason' => $record->remarks,
            ]));
    }

    private static function getEvaluationStatusColor(?string $status): ?string
    {
        return match ($status) {
            'Dipertahankan' => 'success',
            'Perlu direvisi' => 'warning',
            'Perlu diperbaharui' => 'info',
            'Perlu ditarik' => 'warning',
            'Perlu dimusnahkan' => 'danger',
            default => null,
        };
    }
}
