<?php

namespace App\Filament\Resources\ChangeRequests;

use App\Filament\Resources\ChangeRequests\Pages\CreateChangeRequest;
use App\Filament\Resources\ChangeRequests\Pages\EditChangeRequest;
use App\Filament\Resources\ChangeRequests\Pages\ListChangeRequests;
use App\Filament\Resources\ChangeRequests\Schemas\ChangeRequestForm;
use App\Filament\Resources\ChangeRequests\Tables\ChangeRequestsTable;
use App\Models\ChangeRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ChangeRequestResource extends Resource
{
    protected static ?string $model = ChangeRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowUp;

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Dokumen';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Pengajuan Revisi';

    protected static ?string $pluralModelLabel = 'Pengajuan Revisi';

    protected static ?string $slug = 'change-requests';

    public static function form(Schema $schema): Schema
    {
        return ChangeRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChangeRequestsTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChangeRequests::route('/'),
            'create' => CreateChangeRequest::route('/create'),
            'edit' => EditChangeRequest::route('/{record}/edit'),
        ];
    }
}
