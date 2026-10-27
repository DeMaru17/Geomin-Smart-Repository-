<?php

namespace App\Filament\Resources\DocumentImportBatches\Infolists;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

class DocumentImportBatchInfolist
{
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Informasi Batch Sinkronisasi')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(12)->schema([
                            TextEntry::make('batch_number')
                                ->label('Nomor Batch')
                                ->icon('heroicon-m-rectangle-stack')
                                ->weight(FontWeight::Bold)
                                ->size(TextSize::Large)
                                ->color('primary')
                                ->columnSpan(['lg' => 6]),
                            TextEntry::make('status')
                                ->label('Status Batch')
                                ->badge()
                                ->color(fn(?string $state): string => match ($state) {
                                    'Completed' => 'success',
                                    'Preview_Ready' => 'info',
                                    'Partial_Scan' => 'warning',
                                    'Processing' => 'primary',
                                    'Cancelled' => 'danger',
                                    default => 'gray',
                                })
                                ->columnSpan(['lg' => 6]),
                        ]),
                        Grid::make(12)->schema([
                            TextEntry::make('source_directory')
                                ->label('Direktori Sumber Impor')
                                ->icon('heroicon-m-folder')
                                ->color('gray')
                                ->default('document-import')
                                ->columnSpan(['lg' => 4]),
                            TextEntry::make('scanner.name')
                                ->label('Petugas Pemindai')
                                ->icon('heroicon-m-user')
                                ->default('Sistem / Admin')
                                ->columnSpan(['lg' => 4]),
                            TextEntry::make('created_at')
                                ->label('Waktu Pemindaian')
                                ->icon('heroicon-m-calendar')
                                ->dateTime('d M Y, H:i:s')
                                ->columnSpan(['lg' => 4]),
                        ]),
                    ]),

                Section::make('Statistik Pemrosesan Berkas')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(5)->schema([
                            TextEntry::make('total_files_scanned')
                                ->label('Total Dipindai')
                                ->numeric()
                                ->badge()
                                ->color('gray'),
                            TextEntry::make('total_matched')
                                ->label('Cocok Otomatis')
                                ->numeric()
                                ->badge()
                                ->color('info'),
                            TextEntry::make('total_installed')
                                ->label('Berhasil Dipasang')
                                ->numeric()
                                ->badge()
                                ->color('success'),
                            TextEntry::make('total_failed')
                                ->label('Gagal Dipasang')
                                ->numeric()
                                ->badge()
                                ->color('danger'),
                            TextEntry::make('total_skipped')
                                ->label('Dilewati / Konflik')
                                ->numeric()
                                ->badge()
                                ->color('warning'),
                        ]),
                    ]),
            ]);
    }
}
