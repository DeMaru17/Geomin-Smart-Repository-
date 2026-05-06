<?php

namespace App\Filament\Resources\IsoClauses;

use App\Filament\Resources\IsoClauses\Pages\CreateIsoClause;
use App\Filament\Resources\IsoClauses\Pages\EditIsoClause;
use App\Filament\Resources\IsoClauses\Pages\ListIsoClauses;
use App\Filament\Resources\IsoClauses\Schemas\IsoClauseForm;
use App\Filament\Resources\IsoClauses\Tables\IsoClausesTable;
use App\Models\IsoClause;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class IsoClauseResource extends Resource
{
    protected static ?string $model = IsoClause::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookmarkSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return IsoClauseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IsoClausesTable::configure($table);
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
