<?php

namespace App\Filament\Resources\DocumentImportBatches;

use App\Filament\Resources\DocumentImportBatches\Infolists\DocumentImportBatchInfolist;
use App\Filament\Resources\DocumentImportBatches\Pages\ListDocumentImportBatches;
use App\Filament\Resources\DocumentImportBatches\Pages\ViewDocumentImportBatch;
use App\Filament\Resources\DocumentImportBatches\RelationManagers\ItemsRelationManager;
use App\Models\DocumentImportBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class DocumentImportBatchResource extends Resource
{
    protected static ?string $model = DocumentImportBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Dokumen';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Batch Sinkronisasi Dokumen';

    protected static ?string $pluralModelLabel = 'Sinkronisasi Dokumen';

    protected static ?string $recordTitleAttribute = 'batch_number';

    public static function canAccess(): bool
    {
        return auth()->check() && in_array(auth()->user()->role, ['admin', 'manajemen'], true);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DocumentImportBatchInfolist::infolist($schema);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentImportBatches::route('/'),
            'view' => ViewDocumentImportBatch::route('/{record}'),
        ];
    }
}
