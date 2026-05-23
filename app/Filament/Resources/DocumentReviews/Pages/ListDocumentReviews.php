<?php

namespace App\Filament\Resources\DocumentReviews\Pages;

use App\Filament\Resources\DocumentReviews\DocumentReviewResource;
use App\Filament\Resources\DocumentReviews\Widgets\DocumentReviewStatsWidget;
use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\User;
use App\Services\DocumentReviewExportService;
use App\Services\DocumentReviewService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListDocumentReviews extends ListRecords
{
    protected static string $resource = DocumentReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getCatatKajiUlangAction(),
            $this->getExportForm205Action(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            DocumentReviewStatsWidget::class,
        ];
    }

    public function getTabs(): array
    {
        $service = app(DocumentReviewService::class);

        $overdueIds = $service->getOverdueDocuments()->pluck('id')->toArray();
        $dueSoonIds = $service->getDueSoonDocuments()->pluck('id')->toArray();

        return [
            'semua' => Tab::make('Semua'),

            'mendekati' => Tab::make('Mendekati Jadwal')
                ->icon('heroicon-m-clock')
                ->badge(count($dueSoonIds) ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereIn('id', $dueSoonIds)),

            'terlewat' => Tab::make('Terlewat')
                ->icon('heroicon-m-exclamation-triangle')
                ->badge(count($overdueIds) ?: null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereIn('id', $overdueIds)),
        ];
    }

    protected function getCatatKajiUlangAction(): Action
    {
        return Action::make('catat_kaji_ulang')
            ->label('+ Catat Kaji Ulang')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('primary')
            ->visible(fn(): bool => in_array(auth()->user()?->role, ['admin', 'manajemen']))
            ->before(function (): void {
                abort_unless(in_array(auth()->user()?->role, ['admin', 'manajemen']), 403);
            })
            ->form([
                Select::make('document_id')
                    ->label('Dokumen')
                    ->required()
                    ->searchable()
                    ->preload(false)
                    ->getSearchResultsUsing(function (string $search): array {
                        return Document::query()
                            ->whereHas('revisions', fn(Builder $q) => $q->whereIn('status', ['Published', 'Terbit']))
                            ->where(function (Builder $q) use ($search) {
                                $q->where('document_number', 'like', "%{$search}%")
                                    ->orWhere('title', 'like', "%{$search}%");
                            })
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn(Document $doc) => [
                                $doc->id => $doc->document_number . ' - ' . $doc->title,
                            ])
                            ->toArray();
                    })
                    ->getOptionLabelUsing(function ($value): ?string {
                        $document = Document::find($value);

                        return $document ? $document->document_number . ' - ' . $document->title : null;
                    }),

                DatePicker::make('review_date')
                    ->label('Tanggal Kaji Ulang')
                    ->required()
                    ->default(now()),

                Select::make('evaluation_status')
                    ->label('Status Evaluasi')
                    ->required()
                    ->options([
                        'Perlu direvisi' => 'Perlu direvisi',
                        'Perlu diperbaharui' => 'Perlu diperbaharui',
                        'Perlu ditarik' => 'Perlu ditarik',
                        'Perlu dimusnahkan' => 'Perlu dimusnahkan',
                        'Dipertahankan' => 'Dipertahankan',
                    ]),

                Textarea::make('remarks')
                    ->label('Catatan')
                    ->maxLength(1000),
            ])
            ->action(function (array $data): void {
                DocumentReview::create([
                    'document_id' => $data['document_id'],
                    'review_date' => $data['review_date'],
                    'reviewer_id' => auth()->id(),
                    'evaluation_status' => $data['evaluation_status'],
                    'remarks' => $data['remarks'] ?? null,
                ]);

                Notification::make()
                    ->title('Kaji ulang berhasil dicatat')
                    ->success()
                    ->send();
            });
    }

    protected function getExportForm205Action(): Action
    {
        $currentYear = now()->year;

        $defaultDiperiksaOleh = User::where('jabatan', 'Asisten Manajer')->first()?->name ?? '';
        $defaultDisetujuiOleh = User::where('jabatan', 'Manajer')->first()?->name ?? '';

        return Action::make('export_form_205')
            ->label('Cetak Evaluasi Kaji Ulang Dokumen')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->visible(fn(): bool => in_array(auth()->user()?->role, ['admin', 'manajemen']))
            ->before(function (): void {
                abort_unless(in_array(auth()->user()?->role, ['admin', 'manajemen']), 403);
            })
            ->form([
                Select::make('periode_bulan')
                    ->label('Periode Bulan')
                    ->required()
                    ->options([
                        'all' => 'Semua Bulan',
                        '1' => 'Januari',
                        '2' => 'Februari',
                        '3' => 'Maret',
                        '4' => 'April',
                        '5' => 'Mei',
                        '6' => 'Juni',
                        '7' => 'Juli',
                        '8' => 'Agustus',
                        '9' => 'September',
                        '10' => 'Oktober',
                        '11' => 'November',
                        '12' => 'Desember',
                    ]),

                Select::make('periode_tahun')
                    ->label('Periode Tahun')
                    ->required()
                    ->options(function () use ($currentYear): array {
                        $options = [];
                        for ($year = $currentYear - 3; $year <= $currentYear; $year++) {
                            $options[$year] = (string) $year;
                        }

                        return $options;
                    })
                    ->default($currentYear),

                TextInput::make('disiapkan_oleh')
                    ->label('Disiapkan Oleh')
                    ->required()
                    ->maxLength(100)
                    ->default(fn() => auth()->user()?->name ?? ''),

                TextInput::make('diperiksa_oleh')
                    ->label('Diperiksa Oleh')
                    ->required()
                    ->maxLength(100)
                    ->default($defaultDiperiksaOleh),

                TextInput::make('disetujui_oleh')
                    ->label('Disetujui Oleh')
                    ->required()
                    ->maxLength(100)
                    ->default($defaultDisetujuiOleh),
            ])
            ->action(function (array $data) {
                $service = app(DocumentReviewExportService::class);

                $periodeBulan = $data['periode_bulan'] === 'all' ? null : $data['periode_bulan'];

                $response = $service->export(
                    periodeBulan: $periodeBulan,
                    periodeTahun: (int) $data['periode_tahun'],
                    disiapkanOleh: $data['disiapkan_oleh'],
                    diperiksaOleh: $data['diperiksa_oleh'],
                    disetujuiOleh: $data['disetujui_oleh'],
                );

                if ($response === false) {
                    $templatePath = storage_path('app/templates/273.106.205 Evaluasi Kaji Ulang Dokumen Rev. 01.xlsx');

                    if (! file_exists($templatePath)) {
                        Notification::make()
                            ->title('Template file tidak ditemukan')
                            ->body('File template Excel Form 205 tidak tersedia di storage.')
                            ->danger()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Tidak ada data')
                            ->body('Tidak ada record kaji ulang untuk periode yang dipilih.')
                            ->warning()
                            ->send();
                    }

                    return;
                }

                return $response;
            });
    }
}
