<?php

namespace App\Filament\Resources\Documents;

use App\Filament\Resources\Documents\Infolists\HeaderActions;
use App\Filament\Resources\Documents\Infolists\RevisionActions;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Models\Document;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
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
                    ->relationship('category', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('department_id')
                    ->label('Departemen')
                    ->relationship('department', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('isoClauses')
                    ->label('Klausul ISO Terkait')
                    ->relationship('isoClauses', 'clause_number')
                    ->multiple()
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
                TextColumn::make('isoClauses')
                    ->label('Klausul ISO')
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->limitList(2)
                    ->formatStateUsing(fn($state) => $state->clause_number . ' - ' . $state->description),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name'),
                SelectFilter::make('department_id')
                    ->label('Divisi')
                    ->relationship('department', 'name'),
                TernaryFilter::make('is_external')
                    ->label('Jenis Dokumen')
                    ->placeholder('Semua Jenis')
                    ->trueLabel('Dokumen Eksternal')
                    ->falseLabel('Dokumen Internal'),
                SelectFilter::make('isoClauses')
                    ->label('Klausul ISO')
                    ->multiple()
                    ->relationship('isoClauses', 'clause_number')
                    ->getOptionLabelFromRecordUsing(fn($record) => $record->clause_number . ' - ' . $record->description)
                    ->searchable()
                    ->preload(false),
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

                            return $revision ? route('secure.viewer', ['id' => $revision->id]) : '#';
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
                                // BAGIAN KIRI: Informasi Dokumen
                                Group::make([
                                    Grid::make(12)->schema([
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
                                    TextEntry::make('title')
                                        ->hiddenLabel()
                                        ->weight(FontWeight::ExtraBold)
                                        ->size(TextSize::Large)
                                        ->extraAttributes(['class' => 'text-2xl mt-2 mb-4']),
                                    Grid::make(4)->schema([
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
                                    TextEntry::make('isoClauses')
                                        ->label('Klausul ISO:')
                                        ->inlineLabel()
                                        ->badge()
                                        ->color('info')
                                        ->formatStateUsing(fn($state) => $state->clause_number . ' - ' . $state->description)
                                        ->extraAttributes(['class' => 'mt-4']),
                                ])->columnSpan(['lg' => 6]),

                                // BAGIAN KANAN: Tombol Aksi
                                Group::make([
                                    Actions::make(HeaderActions::make())
                                        ->alignment(Alignment::End),
                                ])->columnSpan(['lg' => 6]),
                            ]),
                    ]),

                // 2. MAIN TABS
                Tabs::make('Tabs')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Ringkasan')
                            ->schema([
                                Grid::make(3)->schema([
                                    Group::make([
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

                                        Section::make('Statistik Dokumen')
                                            ->schema([
                                                Grid::make(3)->schema([
                                                    TextEntry::make('revisions_count')
                                                        ->hiddenLabel()
                                                        ->state(fn($record) => $record->revisions()->count())
                                                        ->formatStateUsing(fn($state) => "<div class='text-center py-3'><span class='text-3xl font-bold'>{$state}</span><br><span class='text-sm text-gray-500'>Total Revisi</span></div>")
                                                        ->html()
                                                        ->extraAttributes(['class' => 'rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 bg-white dark:bg-gray-900 p-2']),
                                                    TextEntry::make('total_akses')
                                                        ->hiddenLabel()
                                                        ->state(fn($record) => \App\Models\DocumentDistributionLog::whereIn('document_revision_id', $record->revisions()->pluck('id'))->count())
                                                        ->formatStateUsing(fn($state) => "<div class='text-center py-3'><span class='text-3xl font-bold'>{$state}</span><br><span class='text-sm text-gray-500'>Total Akses</span></div>")
                                                        ->html()
                                                        ->extraAttributes(['class' => 'rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 bg-white dark:bg-gray-900 p-2']),
                                                    TextEntry::make('permintaan_revisi')
                                                        ->hiddenLabel()
                                                        ->state(fn($record) => $record->revisions()->where('status', 'In_Review')->count())
                                                        ->formatStateUsing(fn($state) => "<div class='text-center py-3'><span class='text-3xl font-bold'>{$state}</span><br><span class='text-sm text-gray-500'>Permintaan Revisi</span></div>")
                                                        ->html()
                                                        ->extraAttributes(['class' => 'rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 bg-white dark:bg-gray-900 p-2']),
                                                ]),
                                            ]),
                                    ])->columnSpan(['lg' => 2]),

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
                                        Actions::make(RevisionActions::make()),
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
