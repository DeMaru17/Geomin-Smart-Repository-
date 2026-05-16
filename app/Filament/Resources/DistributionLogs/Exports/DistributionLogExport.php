<?php

namespace App\Filament\Resources\DistributionLogs\Exports;

use PhpOffice\PhpSpreadsheet\Shared\Date;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class DistributionLogExport extends ExcelExport
{
    public function setUp(): void
    {
        $this->withFilename('Form-273.106.201-Distribusi-Dokumen');

        $this->useTableQuery();

        $this->modifyQueryUsing(function ($query) {
            return $query
                ->where('action', '!=', 'Verifikasi Fisik (QR)')
                ->with(['user', 'documentRevision.document.category']);
        });

        $this->ignoreFormatting(['no', 'tanggal']);

        $this->withColumns([
            Column::make('no')
                ->heading('No')
                ->getStateUsing(fn() => null)
                ->formatStateUsing(fn($state) => $state),

            Column::make('tanggal')
                ->heading('Tanggal')
                ->format('dd-mm-yyyy')
                ->getStateUsing(fn($record) => Date::dateTimeToExcel($record->accessed_at))
                ->formatStateUsing(fn($state) => $state),

            Column::make('nama_dokumen')
                ->heading('Nama Dokumen')
                ->getStateUsing(function ($record) {
                    $revision = $record->documentRevision;
                    $document = $revision?->document;
                    if (! $document) {
                        return '-';
                    }

                    $revNumber = str_pad($revision->revision_number ?? '0', 2, '0', STR_PAD_LEFT);

                    return $document->document_number . ' - ' . $document->title . ' (Rev. ' . $revNumber . ')';
                })
                ->formatStateUsing(fn($state) => $state),

            Column::make('jenis_dokumen')
                ->heading('Jenis Dokumen')
                ->getStateUsing(function ($record) {
                    return $record->documentRevision?->document?->category?->name ?? '-';
                })
                ->formatStateUsing(fn($state) => $state),

            Column::make('penerima')
                ->heading('Penerima')
                ->getStateUsing(fn($record) => $record->recipient_name)
                ->formatStateUsing(fn($state) => $state),

            Column::make('tanda_tangan')
                ->heading('Tanda Tangan')
                ->getStateUsing(function ($record) {
                    $userName = $record->user?->name;

                    return $userName ? 'Tercatat di sistem: ' . $userName : '-';
                })
                ->formatStateUsing(fn($state) => $state),

            Column::make('keterangan')
                ->heading('Keterangan')
                ->getStateUsing(function ($record) {
                    if ($record->purpose) {
                        return $record->action . ' - ' . $record->purpose;
                    }

                    return $record->action;
                })
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
