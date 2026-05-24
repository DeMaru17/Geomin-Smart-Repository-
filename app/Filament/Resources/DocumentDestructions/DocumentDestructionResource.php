<?php

namespace App\Filament\Resources\DocumentDestructions;

use App\Filament\Resources\DocumentDestructions\Schemas\DocumentDestructionForm;
use App\Filament\Resources\DocumentDestructions\Tables\DocumentDestructionsTable;
use App\Models\DocumentDestruction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DocumentDestructionResource extends Resource
{
    protected static ?string $model = DocumentDestruction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMinus;

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Dokumen';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Pemusnahan Dokumen';

    protected static ?string $pluralModelLabel = 'Pemusnahan Dokumen';

    protected static ?string $slug = 'document-destructions';

    public static function form(Schema $schema): Schema
    {
        return DocumentDestructionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentDestructionsTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentDestructions::route('/'),
            'create' => Pages\CreateDocumentDestruction::route('/create'),
        ];
    }
}
