<?php

namespace App\Filament\Resources\QualityRecords\Schemas;

use App\Models\Document;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class QualityRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('document_id')
                    ->label('Dokumen Induk')
                    ->required()
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search): array {
                        return Document::query()
                            ->whereHas('category', fn($query) => $query->where('code', 'F'))
                            ->whereIn('status', ['Published', 'Active'])
                            ->where(function ($query) use ($search) {
                                $query->where('document_number', 'like', "%{$search}%")
                                    ->orWhere('title', 'like', "%{$search}%");
                            })
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn(Document $record): array => [
                                $record->id => "{$record->document_number} - {$record->title}",
                            ])
                            ->toArray();
                    })
                    ->getOptionLabelUsing(function ($value): ?string {
                        $record = Document::find($value);

                        return $record ? "{$record->document_number} - {$record->title}" : null;
                    }),

                TextInput::make('record_name')
                    ->label('Nama Rekaman')
                    ->required()
                    ->maxLength(255),

                DatePicker::make('record_date')
                    ->label('Tanggal Rekaman')
                    ->required()
                    ->default(now()),

                Select::make('confidentiality')
                    ->label('Klasifikasi Kerahasiaan')
                    ->required()
                    ->default('Biasa')
                    ->options([
                        'Sangat Rahasia' => 'Sangat Rahasia',
                        'Rahasia' => 'Rahasia',
                        'Biasa' => 'Biasa',
                    ]),

                TextInput::make('physical_location')
                    ->label('Lokasi Fisik')
                    ->maxLength(255),

                Textarea::make('description')
                    ->label('Deskripsi')
                    ->maxLength(1000),

                FileUpload::make('file_path')
                    ->label('File Rekaman')
                    ->required()
                    ->directory('quality-records')
                    ->acceptedFileTypes([
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'image/jpeg',
                        'image/png',
                    ])
                    ->maxSize(10240),
            ]);
    }
}
