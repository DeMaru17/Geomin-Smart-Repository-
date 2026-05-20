<?php

namespace App\Filament\Resources\ChangeRequests\Schemas;

use App\Filament\Resources\ChangeRequests\Pages\EditChangeRequest;
use App\Models\Document;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ChangeRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('document_id')
                    ->label('Dokumen')
                    ->required()
                    ->relationship('document', 'title')
                    ->searchable()
                    ->preload(false)
                    ->getOptionLabelFromRecordUsing(fn(Document $record): string => "{$record->document_number} - {$record->title}")
                    ->disabled(fn($livewire): bool => $livewire instanceof EditChangeRequest)
                    ->dehydrated(),
                DatePicker::make('proposal_date')
                    ->label('Tanggal Pengajuan')
                    ->required()
                    ->default(now()),
                Textarea::make('reason')
                    ->label('Alasan Perubahan')
                    ->required()
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
            ]);
    }
}
