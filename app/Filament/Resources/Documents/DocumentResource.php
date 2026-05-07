<?php

namespace App\Filament\Resources\Documents;

use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Models\Document;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 4;

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
            ->columns([
                TextColumn::make('document_number')
                    ->label('Nomor Dokumen')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('title')
                    ->label('Judul Dokumen')
                    ->searchable()
                    ->sortable(),

                // Mengubah category_id menjadi relasi nama
                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->sortable(),

                // Mengubah department_id menjadi relasi nama
                TextColumn::make('department.name')
                    ->label('Departemen')
                    ->sortable(),

                IconColumn::make('is_external')
                    ->label('Eksternal')
                    ->boolean(),

                TextColumn::make('retention_period_months')
                    ->label('Retensi (Bulan)')
                    ->numeric()
                    ->sortable(),
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
            'edit' => EditDocument::route('/{record}/edit'),
        ];
    }
}
