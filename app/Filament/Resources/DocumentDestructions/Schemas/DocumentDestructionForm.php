<?php

namespace App\Filament\Resources\DocumentDestructions\Schemas;

use App\Filament\Resources\DocumentDestructions\Pages\CreateDocumentDestruction;
use App\Models\Document;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class DocumentDestructionForm
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
                    ->getSearchResultsUsing(function (string $search): array {
                        return Document::query()
                            ->where('document_number', 'like', "%{$search}%")
                            ->orWhere('title', 'like', "%{$search}%")
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
                    })
                    ->disabled(fn($livewire): bool => $livewire instanceof CreateDocumentDestruction && $livewire->getDocumentIdDisabled())
                    ->dehydrated(),

                Textarea::make('reason')
                    ->label('Alasan Pemusnahan')
                    ->required()
                    ->disabled(fn($livewire): bool => $livewire instanceof CreateDocumentDestruction && $livewire->getReasonDisabled())
                    ->dehydrated(),

                Select::make('method')
                    ->label('Metode Penarikan')
                    ->required()
                    ->options([
                        'Penarikan Akses Digital' => 'Penarikan Akses Digital',
                        'Penghancuran Dokumen Fisik/Cetak' => 'Penghancuran Dokumen Fisik/Cetak',
                        'Lainnya' => 'Lainnya',
                    ]),

                Hidden::make('proposer_id')
                    ->default(fn() => Auth::id()),

                Hidden::make('status')
                    ->default('Pending'),
            ]);
    }
}
