<?php

namespace App\Filament\Resources\DocumentReviews;

use App\Filament\Resources\DocumentReviews\Infolists\DocumentReviewInfolist;
use App\Filament\Resources\DocumentReviews\Pages\ListDocumentReviews;
use App\Filament\Resources\DocumentReviews\Pages\ViewDocumentReview;
use App\Filament\Resources\DocumentReviews\Tables\DocumentReviewsTable;
use App\Models\Document;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DocumentReviewResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Jadwal Kaji Ulang';

    protected static string|\UnitEnum|null $navigationGroup = 'Kaji Ulang';

    protected static ?string $modelLabel = 'Kaji Ulang Dokumen';

    protected static ?string $pluralModelLabel = 'Jadwal Kaji Ulang';

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'manajemen']);
    }

    public static function getEloquentQuery(): Builder
    {
        return Document::query()
            ->whereHas('revisions', fn(Builder $q) => $q->whereIn('status', ['Published', 'Terbit']))
            ->with(['revisions', 'reviews']);
    }

    public static function table(Table $table): Table
    {
        return DocumentReviewsTable::table($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DocumentReviewInfolist::infolist($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentReviews::route('/'),
            'view' => ViewDocumentReview::route('/{record}'),
        ];
    }
}
