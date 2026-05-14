<?php

namespace App\Filament\Resources\Documents;

use App\Filament\Pages\CompareVersions;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Models\Document;
use App\Services\PdfStamperService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Action as InfolistAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Dokumen';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Document';

    protected static ?string $pluralModelLabel = 'Manajemen Dokumen';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('document_number')
                    ->label('Nomor Dokumen')
                    ->required()
                    ->maxLength(255),
                TextInput::make('title')
                    ->label('Judul Dokumen')
                    ->required()
                    ->maxLength(255),
                Select::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name') // Mengambil nama kategori dari relasi
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('department_id')
                    ->label('Departemen')
                    ->relationship('department', 'name') // Mengambil nama departemen dari relasi
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('isoClauses')
                    ->label('Klausul ISO Terkait')
                    ->relationship('isoClauses', 'clause_number') // Relasi Many-to-Many
                    ->multiple() // Bisa pilih lebih dari 1 klausul
                    ->preload(),
                TextInput::make('retention_period_months')
                    ->label('Masa Retensi (Bulan)')
                    ->numeric()
                    ->default(36)
                    ->required(),
                Toggle::make('is_external')
                    ->label('Dokumen Eksternal?'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([])
            ->description('Repositori dokumen internal dan eksternal')

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

                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('department.name')
                    ->label('Divisi'),

                // Mengambil nomor revisi terbaru dari relasi
                TextColumn::make('revisions_latest.revision_number')
                    ->label('Rev.')
                    ->default('00'),

                TextColumn::make('revisions_latest.status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Published', 'Terbit' => 'success',
                        'Draft' => 'gray',
                        'In_Review' => 'warning',
                        'Approved' => 'info',
                        default => 'primary',
                    }),

                // Menampilkan klausul ISO sebagai tag kecil
                TextColumn::make('isoClauses.clause_number')
                    ->label('Klausul ISO')
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->limitList(2),
            ])

            // Mengubah posisi filter menjadi horizontal di atas tabel
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name'),

                SelectFilter::make('department_id')
                    ->label('Divisi')
                    ->relationship('department', 'name'),

                // Filter baru untuk Dokumen Eksternal/Internal
                TernaryFilter::make('is_external')
                    ->label('Jenis Dokumen')
                    ->placeholder('Semua Jenis')
                    ->trueLabel('Dokumen Eksternal')
                    ->falseLabel('Dokumen Internal'),

                // Filter baru untuk Klausul ISO
                SelectFilter::make('isoClauses')
                    ->label('Klausul ISO')
                    ->multiple()
                    ->relationship('isoClauses', 'clause_number'),

            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)

            ->actions([
                ActionGroup::make([
                    Action::make('pratinjau')
                        ->label('Pratinjau')
                        ->icon('heroicon-m-eye')
                        ->color('info')
                        ->url(function ($record) {
                            $revision = $record->revisions()
                                ->whereIn('status', ['Published', 'Approved'])
                                ->latest()
                                ->first() ?? $record->revisions()->latest()->first();

                            if (! $revision) {
                                return '#';
                            }

                            return route('secure.viewer', ['id' => $revision->id]);
                        })
                        ->openUrlInNewTab(),
                    ViewAction::make(),
                    EditAction::make()
                        ->visible(fn() => in_array(auth()->user()?->role, ['admin', 'manajemen'])),
                ])->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip('Aksi'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // 1. HEADER (Hero Section)
                Section::make()
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                // BAGIAN KIRI: Informasi Dokumen (Mengambil 8 Kolom)
                                Group::make([
                                    // Baris 1: Nomor Dokumen & Status
                                    Grid::make(12)
                                        ->schema([
                                            TextEntry::make('document_number')
                                                ->hiddenLabel()
                                                ->icon('heroicon-m-document-text')
                                                ->weight(FontWeight::Bold)
                                                ->color('gray')
                                                ->size(TextSize::Large)
                                                ->columnSpan(['lg' => 4]),

                                            TextEntry::make('revisions_latest.status')
                                                ->hiddenLabel()
                                                ->badge()
                                                ->color(fn(?string $state): string => match ($state) {
                                                    'Published', 'Terbit' => 'success',
                                                    'Draft' => 'gray',
                                                    'In_Review' => 'warning',
                                                    'Approved' => 'info',
                                                    'Obsolete' => 'danger',
                                                    default => 'primary',
                                                })
                                                ->columnSpan(['lg' => 8]),
                                        ]),

                                    // Baris 2: Judul Dokumen
                                    TextEntry::make('title')
                                        ->hiddenLabel()
                                        ->weight(FontWeight::ExtraBold)
                                        ->size(TextSize::Large)
                                        ->extraAttributes(['class' => 'text-2xl mt-2 mb-4']),

                                    // Baris 3: Meta Info (Kategori, Divisi, dll sejajar)
                                    Grid::make(4)
                                        ->schema([
                                            TextEntry::make('category.name')
                                                ->hiddenLabel()
                                                ->icon('heroicon-m-tag')
                                                ->color('gray'),

                                            TextEntry::make('department.name')
                                                ->hiddenLabel()
                                                ->icon('heroicon-m-building-office')
                                                ->color('gray'),

                                            TextEntry::make('retention_period_months')
                                                ->hiddenLabel()
                                                ->icon('heroicon-m-clock')
                                                ->color('gray')
                                                ->formatStateUsing(fn($state) => "Masa simpan: {$state} bulan"),

                                            TextEntry::make('revisions_latest.revision_number')
                                                ->hiddenLabel()
                                                ->icon('heroicon-m-arrow-path')
                                                ->color('gray')
                                                ->formatStateUsing(fn($state) => 'Rev. ' . str_pad($state ?? '0', 2, '0', STR_PAD_LEFT)),
                                        ]),

                                    // Baris 4: Klausul ISO
                                    TextEntry::make('isoClauses.clause_number')
                                        ->label('Klausul ISO:')
                                        ->inlineLabel()
                                        ->badge()
                                        ->color('info')
                                        ->extraAttributes(['class' => 'mt-4']),

                                ])->columnSpan(['lg' => 6]), // Berikan 6 kolom untuk info

                                // BAGIAN KANAN: Tombol Aksi (Mengambil sisa 6 Kolom)
                                Group::make([
                                    Actions::make([
                                        InfolistAction::make('bandingkan')
                                            ->label('Bandingkan Versi')
                                            ->icon('heroicon-m-arrows-right-left')
                                            ->color('gray')
                                            ->url(fn($record) => CompareVersions::getUrl() . '?document_id=' . $record->id),

                                        InfolistAction::make('cetak_qr_code')
                                            ->label('Cetak QR Code')
                                            ->icon('heroicon-m-qr-code')
                                            ->color('gray')
                                            ->visible(fn() => auth()->user()?->role === 'admin')
                                            ->modalHeading('Cetak QR Code')
                                            ->modalContent(function ($record) {
                                                $url = config('app.url') . '/dokumen/aktif/' . $record->document_number;
                                                $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::size(250)->generate($url);
                                                $namaDokumen = $record->document_number . ' - ' . $record->title;

                                                $html = <<<HTML
                                                    <div>
                                                        <div id="qr-print-area" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1.5rem; padding: 2rem; text-align: center;">

                                                            <div style="display: block;">
                                                                {$qrSvg}
                                                            </div>

                                                            <div style="font-size: 1.25rem; color: #000; font-family: sans-serif; font-weight: bold; max-width: 320px; word-wrap: break-word; line-height: 1.4;">
                                                                {$namaDokumen}
                                                            </div>

                                                        </div>

                                                        <div style="text-align: center; margin-top: 1rem;">
                                                            <button type="button"
                                                                x-data
                                                                @click="
                                                                    let printWin = window.open('', '', 'width=800,height=800');
                                                                    printWin.document.write('<html><head><title>Cetak QR Code</title>');
                                                                    printWin.document.write('<style>body{margin:0;display:flex;justify-content:center;align-items:center;min-height:100vh;} svg{display:block;}</style>');
                                                                    printWin.document.write('</head><body>');
                                                                    // Gunakan outerHTML agar style flexbox container ikut tercetak
                                                                    printWin.document.write(document.getElementById('qr-print-area').outerHTML);
                                                                    printWin.document.write('</body></html>');
                                                                    printWin.document.close();
                                                                    printWin.focus();
                                                                    setTimeout(() => { printWin.print(); printWin.close(); }, 250);
                                                                "
                                                                class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-sm bg-primary-600 hover:bg-primary-500">
                                                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M7.875 1.5C6.839 1.5 6 2.34 6 3.375v2.99c-.426.053-.851.11-1.274.174-1.454.218-2.476 1.483-2.476 2.917v6.294a3 3 0 0 0 3 3h.27l-.092 1.086a1.875 1.875 0 0 0 1.87 2.04h9.405a1.875 1.875 0 0 0 1.87-2.04l-.093-1.086h.27a3 3 0 0 0 3-3V9.456c0-1.434-1.022-2.7-2.476-2.917A48.716 48.716 0 0 0 18 6.366V3.375c0-1.036-.84-1.875-1.875-1.875h-8.25ZM16.5 6.205v-2.83A.375.375 0 0 0 16.125 3h-8.25a.375.375 0 0 0-.375.375v2.83a49.353 49.353 0 0 1 9 0Zm-.217 8.265c.178.018.317.16.333.337l.526 5.568a.375.375 0 0 1-.374.41H7.232a.375.375 0 0 1-.374-.41l.526-5.568a.345.345 0 0 1 .333-.337 47.636 47.636 0 0 1 8.566 0Z" clip-rule="evenodd"/></svg>
                                                                Cetak
                                                            </button>
                                                        </div>
                                                    </div>
                                                    HTML;
                                                return new \Illuminate\Support\HtmlString($html);
                                            })
                                            ->modalFooterActions([])
                                            ->modalSubmitAction(false)
                                            ->action(fn() => null),

                                        // Tombol Edit
                                        InfolistAction::make('edit')
                                            ->label('Edit')
                                            ->icon('heroicon-m-pencil')
                                            ->color('primary')
                                            ->visible(fn() => in_array(auth()->user()?->role, ['admin', 'manajemen']))
                                            ->url(fn($record) => EditDocument::getUrl(['record' => $record])),

                                        InfolistAction::make('buka_dokumen')
                                            ->label('Buka Dokumen')
                                            ->icon('heroicon-m-eye')
                                            ->color('info')
                                            ->url(function ($record) {
                                                // Mencari revisi Published terakhir
                                                $revision = $record->revisions()
                                                    ->whereIn('status', ['Published', 'Approved'])
                                                    ->latest()
                                                    ->first() ?? $record->revisions()->latest()->first();

                                                if (! $revision) {
                                                    return '#';
                                                }

                                                return route('secure.viewer', ['id' => $revision->id]);
                                            })
                                            ->openUrlInNewTab(),
                                    ])
                                        // Mendorong seluruh barisan tombol rata kanan (End)
                                        ->alignment(Alignment::End),
                                ])->columnSpan(['lg' => 6]),

                            ]),
                    ]),

                // 2. MAIN TABS & SIDEBAR
                Tabs::make('Tabs')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Ringkasan')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        // KOLOM KIRI (Konten Utama)
                                        Group::make([
                                            // Versi Terbit Aktif
                                            Section::make('Versi Terbit Aktif')
                                                ->icon('heroicon-m-check-badge')
                                                ->schema([
                                                    Grid::make(2)->schema([
                                                        TextEntry::make('revisions_latest.revision_number')
                                                            ->label('Nomor Revisi')
                                                            ->formatStateUsing(fn($state) => 'Rev. ' . str_pad($state ?? '0', 2, '0', STR_PAD_LEFT)),

                                                        TextEntry::make('revisions_latest.created_at')
                                                            ->label('Diterbitkan')
                                                            ->date('d M Y'),

                                                        TextEntry::make('revisions_latest.change_summary')
                                                            ->label('Ringkasan Perubahan')
                                                            ->columnSpanFull()
                                                            ->default('Pembuatan awal dokumen.'),
                                                    ]),
                                                ])->extraAttributes(['class' => 'border-success-500 bg-success-50/50 ring-success-500']),

                                            // Statistik Dokumen
                                            Section::make('Statistik Dokumen')
                                                ->schema([
                                                    Grid::make(3)->schema([
                                                        TextEntry::make('revisions_count')
                                                            ->hiddenLabel()
                                                            ->state(fn($record) => $record->revisions()->count())
                                                            ->formatStateUsing(fn($state) => "<div class='text-center'><span class='text-3xl font-bold'>{$state}</span><br><span class='text-sm text-gray-500'>Total Revisi</span></div>")
                                                            ->html(),

                                                        TextEntry::make('total_akses')
                                                            ->hiddenLabel()
                                                            ->default(2)
                                                            ->formatStateUsing(fn($state) => "<div class='text-center'><span class='text-3xl font-bold'>{$state}</span><br><span class='text-sm text-gray-500'>Total Akses</span></div>")
                                                            ->html(),

                                                        TextEntry::make('permintaan_revisi')
                                                            ->hiddenLabel()
                                                            ->default(1)
                                                            ->formatStateUsing(fn($state) => "<div class='text-center'><span class='text-3xl font-bold'>{$state}</span><br><span class='text-sm text-gray-500'>Permintaan Revisi</span></div>")
                                                            ->html(),
                                                    ]),
                                                ]),
                                        ])->columnSpan(['lg' => 2]),

                                        // KOLOM KANAN (Sidebar Informasi)
                                        Group::make([
                                            Section::make('Informasi Dokumen')
                                                ->schema([
                                                    TextEntry::make('category.name')
                                                        ->label('Kategori')
                                                        ->icon('heroicon-m-tag'),

                                                    TextEntry::make('department.name')
                                                        ->label('Divisi')
                                                        ->icon('heroicon-m-building-office'),

                                                    TextEntry::make('retention_period_months')
                                                        ->label('Masa Simpan')
                                                        ->suffix(' bulan')
                                                        ->icon('heroicon-m-clock'),

                                                    TextEntry::make('created_at')
                                                        ->label('Tanggal Dibuat')
                                                        ->date('d M Y')
                                                        ->icon('heroicon-m-calendar'),
                                                ]),

                                            Section::make('Kaji Ulang Berikutnya')
                                                ->icon('heroicon-m-calendar')
                                                ->schema([
                                                    TextEntry::make('kaji_ulang')
                                                        ->hiddenLabel()
                                                        ->default('Belum ada jadwal kaji ulang')
                                                        ->color('warning'),
                                                ])->extraAttributes(['class' => 'border-warning-500 bg-warning-50/50']),
                                        ])->columnSpan(['lg' => 1]),

                                    ]),
                            ]),

                        Tab::make('Riwayat Revisi')
                            ->badge(fn($record) => $record->revisions()->count())
                            ->schema([
                                RepeatableEntry::make('revisions')
                                    ->hiddenLabel()
                                    ->schema([
                                        TextEntry::make('revision_number')->label('Rev.')->weight('bold'),
                                        TextEntry::make('status')
                                            ->badge()
                                            ->color(fn($state) => match ($state) {
                                                'Published', 'Terbit' => 'success',
                                                'Obsolete' => 'danger',
                                                'Draft' => 'gray',
                                                'In_Review' => 'warning',
                                                'Approved' => 'info',
                                                default => 'primary',
                                            }),
                                        TextEntry::make('change_summary')->label('Ringkasan Perubahan'),
                                        TextEntry::make('created_at')->label('Tanggal')->date('d M Y'),

                                        // Menambahkan tombol aksi khusus Infolist
                                        Actions::make([
                                            InfolistAction::make('pratinjau')
                                                ->label('Pratinjau')
                                                ->icon('heroicon-m-eye')
                                                ->color('primary')
                                                ->url(fn($record) => route('secure.viewer', ['id' => $record->id]))
                                                ->openUrlInNewTab(),

                                            InfolistAction::make('unduh_pdf')
                                                ->label('PDF')
                                                ->icon('heroicon-m-arrow-down-tray')
                                                ->color('danger')
                                                ->visible(fn() => in_array(auth()->user()?->role, ['admin', 'manajemen']))
                                                ->action(fn($record) => response()->download(Storage::path($record->file_path), "{$record->document->document_number} - {$record->document->title} - Rev {$record->revision_number}.pdf")),

                                            InfolistAction::make('unduh_word')
                                                ->label('Word')
                                                ->icon('heroicon-m-document-text')
                                                ->color('info')
                                                ->visible(fn($record) => in_array(auth()->user()?->role, ['admin', 'manajemen']) && $record->word_file_path)
                                                ->action(function ($record) {
                                                    $ext = pathinfo($record->word_file_path, PATHINFO_EXTENSION);

                                                    return response()->download(Storage::path($record->word_file_path), "{$record->document->document_number} - {$record->document->title} - Rev {$record->revision_number}.{$ext}");
                                                }),

                                            InfolistAction::make('terkendali')
                                                ->label('Terkendali')
                                                ->icon('heroicon-m-printer')
                                                ->color('success')
                                                ->visible(fn() => in_array(auth()->user()?->role, ['admin', 'manajemen']))
                                                ->action(function ($record) {
                                                    if (empty($record->file_path) || ! Storage::exists($record->file_path)) {
                                                        Notification::make()
                                                            ->title('Gagal')
                                                            ->body('File PDF tidak tersedia untuk revisi ini.')
                                                            ->danger()
                                                            ->send();

                                                        return;
                                                    }

                                                    $pdfContent = app(PdfStamperService::class)->stampControlled($record);
                                                    $filename = "{$record->document->document_number} - {$record->document->title} (Rev {$record->revision_number}) - Controlled.pdf";

                                                    return response()->streamDownload(function () use ($pdfContent) {
                                                        echo $pdfContent;
                                                    }, $filename, [
                                                        'Content-Type' => 'application/pdf',
                                                    ]);
                                                }),

                                            InfolistAction::make('tidak_terkendali')
                                                ->label('Tidak Terkendali')
                                                ->icon('heroicon-m-arrow-down-tray')
                                                ->color('info')
                                                ->visible(fn($record) => in_array(auth()->user()?->role, ['admin', 'manajemen']) && ! empty($record->file_path))
                                                ->action(function ($record) {
                                                    try {
                                                        $pdfContent = app(PdfStamperService::class)->stampUncontrolled($record);
                                                        $filename = "{$record->document->document_number} - {$record->document->title} (Rev {$record->revision_number}) - Uncontrolled.pdf";

                                                        return response()->streamDownload(function () use ($pdfContent) {
                                                            echo $pdfContent;
                                                        }, $filename, [
                                                            'Content-Type' => 'application/pdf',
                                                        ]);
                                                    } catch (\RuntimeException $e) {
                                                        Notification::make()
                                                            ->title('Gagal')
                                                            ->body('Gagal membuat PDF tidak terkendali')
                                                            ->danger()
                                                            ->send();
                                                    }
                                                }),
                                        ]),
                                    ])
                                    ->columns(5),
                            ]),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RevisionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocuments::route('/'),
            'create' => CreateDocument::route('/create'),
            'view' => Pages\ViewDocument::route('/{record}'),
            'edit' => EditDocument::route('/{record}/edit'),
        ];
    }
}
