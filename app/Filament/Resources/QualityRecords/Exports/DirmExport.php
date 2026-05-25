<?php

namespace App\Filament\Resources\QualityRecords\Exports;

use App\Models\Document;
use App\Models\QualityRecord;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class DirmExport extends ExcelExport
{
    public function setUp(): void
    {
        $this->withFilename('273.103.203 Form Daftar Induk Rekaman Mutu_' . now()->format('dmY'));

        $this->withColumns([
            Column::make('no_form')
                ->heading('No Form')
                ->getStateUsing(fn($record) => $record->document_number),

            Column::make('judul_dokumen')
                ->heading('Judul Dokumen')
                ->getStateUsing(fn($record) => $record->title),

            Column::make('binder')
                ->heading('Binder')
                ->getStateUsing(function ($record) {
                    $latestQualityRecord = QualityRecord::where('document_id', $record->id)
                        ->latest('id')
                        ->first();

                    return $latestQualityRecord?->physical_location;
                }),

            Column::make('revisi')
                ->heading('Revisi')
                ->getStateUsing(function ($record) {
                    $publishedRevision = $record->revisions
                        ->filter(fn($rev) => in_array($rev->status, ['Published', 'Terbit', 'Active']))
                        ->sortByDesc('revision_number')
                        ->first();

                    if (! $publishedRevision) {
                        return null;
                    }

                    return str_pad($publishedRevision->revision_number ?? '0', 2, '0', STR_PAD_LEFT);
                }),
        ]);
    }

    public function query(): Builder
    {
        return Document::query()
            ->whereHas('category', fn($q) => $q->where('code', 'F'))
            ->whereIn('status', ['Published', 'Terbit', 'Active'])
            ->orderBy('document_number', 'asc')
            ->with(['revisions', 'category']);
    }
}
