<?php

namespace App\Filament\Resources\QualityRecords\Pages;

use App\Filament\Resources\QualityRecords\Exports\DirmExport;
use App\Filament\Resources\QualityRecords\QualityRecordResource;
use Carbon\Carbon;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\ExportAction;

class ListQualityRecords extends ListRecords
{
    protected static string $resource = QualityRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->label('Export DIRM')
                ->exports([
                    DirmExport::make(),
                ]),
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $today = Carbon::today();
        $nearExpirationDate = $today->copy()->addDays(30);

        return [
            'aktif' => Tab::make('Semua Rekaman Aktif')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'Aktif')),

            'mendekati' => Tab::make('Mendekati Kedaluwarsa')
                ->modifyQueryUsing(
                    fn(Builder $query) => $query
                        ->where('status', 'Aktif')
                        ->whereNotNull('expiration_date')
                        ->where('expiration_date', '<=', $nearExpirationDate)
                        ->where('expiration_date', '>', $today)
                ),

            'kedaluwarsa' => Tab::make('Kedaluwarsa')
                ->modifyQueryUsing(
                    fn(Builder $query) => $query
                        ->where('status', 'Aktif')
                        ->whereNotNull('expiration_date')
                        ->where('expiration_date', '<', $today)
                ),

            'dimusnahkan' => Tab::make('Telah Dimusnahkan')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'Dimusnahkan')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'aktif';
    }
}
