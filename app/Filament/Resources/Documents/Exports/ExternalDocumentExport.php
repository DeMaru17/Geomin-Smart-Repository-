<?php

namespace App\Filament\Resources\Documents\Exports;

use App\Models\Document;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ExternalDocumentExport extends ExcelExport
{
    public function setUp(): void
    {
        $this->withFilename('Form-273.106.203-Daftar-Induk-Dokumen-Eksternal');

        $this->fromModel(Document::class);

        $this->modifyQueryUsing(function ($query) {
            return $query
                ->where('is_external', true)
                ->orderBy('document_number', 'asc')
                ->with(['revisions']);
        });

        $this->except([
            'id',
            'document_number',
            'title',
            'category_id',
            'department_id',
            'is_external',
            'publication_year',
            'remarks',
            'retention_period_months',
            'created_at',
            'updated_at',
        ]);

        $this->withColumns([
            Column::make('no')
                ->heading('No')
                ->getStateUsing(fn() => null)
                ->formatStateUsing(fn($state) => $state),

            Column::make('no_dokumen')
                ->heading('No. Dokumen')
                ->getStateUsing(fn($record) => $record->document_number)
                ->formatStateUsing(fn($state) => $state),

            Column::make('nama_dokumen')
                ->heading('Nama Dokumen')
                ->getStateUsing(fn($record) => $record->title)
                ->formatStateUsing(fn($state) => $state),

            Column::make('tahun')
                ->heading('Tahun')
                ->getStateUsing(fn($record) => $record->publication_year)
                ->formatStateUsing(fn($state) => $state),

            Column::make('revisi')
                ->heading('Revisi')
                ->getStateUsing(function ($record) {
                    $publishedRevision = $record->revisions
                        ->filter(fn($rev) => in_array($rev->status, ['Published', 'Terbit']))
                        ->sortByDesc('revision_date')
                        ->first();

                    if (! $publishedRevision) {
                        return '00';
                    }

                    return str_pad($publishedRevision->revision_number ?? '0', 2, '0', STR_PAD_LEFT);
                })
                ->formatStateUsing(fn($state) => $state),

            Column::make('keterangan')
                ->heading('Keterangan')
                ->getStateUsing(fn($record) => $record->remarks)
                ->formatStateUsing(fn($state) => $state),
        ]);
    }

    private int $rowNumber = 0;

    public function map($record): array
    {
        $this->rowNumber++;

        $result = parent::map($record);

        $result['no'] = $this->rowNumber;

        return $result;
    }
}
