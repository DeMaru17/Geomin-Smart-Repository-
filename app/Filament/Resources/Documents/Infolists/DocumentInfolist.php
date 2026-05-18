<?php

namespace App\Filament\Resources\Documents\Infolists;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
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

class DocumentInfolist
{
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
                                                    TextEntry::make('revisions_latest.revision_date')
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
                                                TextEntry::make('revisions_latest.revision_date')
                                                    ->label('Tanggal Revisi Terakhir')
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
                                        TextEntry::make('revision_date')->label('Tanggal Revisi')->date('d M Y'),
                                        Actions::make(RevisionActions::make()),
                                    ])
                                    ->columns(5),
                            ]),
                    ]),
            ]);
    }
}
