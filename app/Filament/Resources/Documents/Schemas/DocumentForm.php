<?php

namespace App\Filament\Resources\Documents\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class DocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                    ->required(fn(Get $get): bool => ! (bool) $get('is_external'))
                    ->searchable()
                    ->preload(),
                Select::make('department_id')
                    ->label('Departemen')
                    ->relationship('department', 'name')
                    ->required(fn(Get $get): bool => ! (bool) $get('is_external'))
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
                    ->label('Dokumen Eksternal?')
                    ->live()
                    ->afterStateUpdated(function (Set $set, $state) {
                        if (! $state) {
                            $set('publication_year', null);
                            $set('remarks', null);
                        }
                    }),
                Section::make('Informasi Dokumen Eksternal')
                    ->visible(fn(Get $get): bool => (bool) $get('is_external'))
                    ->schema([
                        TextInput::make('publication_year')
                            ->label('Tahun Terbit')
                            ->numeric()
                            ->required(fn(Get $get): bool => (bool) $get('is_external'))
                            ->minValue(1900)
                            ->maxValue((int) date('Y'))
                            ->rules(['digits:4'])
                            ->validationMessages([
                                'required' => 'Tahun terbit wajib diisi',
                                'min_value' => 'Tahun terbit harus antara 1900 dan ' . date('Y'),
                                'max_value' => 'Tahun terbit harus antara 1900 dan ' . date('Y'),
                                'digits' => 'Tahun terbit harus antara 1900 dan ' . date('Y'),
                            ]),
                        Textarea::make('remarks')
                            ->label('Keterangan')
                            ->maxLength(500)
                            ->validationMessages([
                                'max' => 'Keterangan maksimal 500 karakter',
                            ]),
                    ]),
            ]);
    }
}
