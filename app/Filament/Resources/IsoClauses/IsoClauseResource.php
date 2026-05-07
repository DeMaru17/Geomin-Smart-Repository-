<?php

namespace App\Filament\Resources\IsoClauses;

use App\Filament\Resources\IsoClauses\Pages\CreateIsoClause;
use App\Filament\Resources\IsoClauses\Pages\EditIsoClause;
use App\Filament\Resources\IsoClauses\Pages\ListIsoClauses;
use App\Models\IsoClause;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IsoClauseResource extends Resource
{
    protected static ?string $model = IsoClause::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookmarkSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'description';

    protected static ?string $pluralModelLabel = 'Klausul ISO';

    protected static ?string $modelLabel = 'Klausul ISO';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('clause_number')
                    ->label('Nomor Klausul')
                    ->required()
                    ->placeholder('Contoh: 4.4')
                    ->maxLength(50),
                TextInput::make('description')
                    ->label('Deskripsi Klausul')
                    ->required()
                    ->placeholder('Contoh: Kerahasiaan')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('clause_number')
                    ->label('Nomor Klausul')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Deskripsi Klausul')
                    ->searchable()
                    ->sortable(),
            ])
            ->actions([
                EditAction::make()->iconButton(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIsoClauses::route('/'),
            'create' => CreateIsoClause::route('/create'),
            'edit' => EditIsoClause::route('/{record}/edit'),
        ];
    }
}
