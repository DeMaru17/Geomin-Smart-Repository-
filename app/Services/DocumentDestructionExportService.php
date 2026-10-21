<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentDestruction;
use App\Models\DocumentDestructionGroup;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DocumentDestructionExportService
{
    /**
     * Path ke template Excel resmi Form 273.106.207.
     */
    public function getTemplatePath(): string
    {
        return storage_path('app/templates/273.106.207 Berita Acara Pemusnahan Dokumen.xlsx');
    }

    /**
     * Generate the export filename for a DocumentDestruction or DocumentDestructionGroup.
     *
     * Format: BAPD_[sanitized-number].xlsx
     * Sanitizes / and \ to -
     */
    public function getFilename(DocumentDestruction|DocumentDestructionGroup $record): string
    {
        if ($record instanceof DocumentDestructionGroup) {
            $number = $record->bapd_number;
        } else {
            $number = $record->group?->bapd_number ?? $record->bapd_number ?? 'unknown';
        }

        $sanitizedNumber = str_replace(['/', '\\'], '-', $number);

        return "BAPD_{$sanitizedNumber}.xlsx";
    }

    /**
     * Muat template Excel resmi, isi data BAPD, dan kembalikan Spreadsheet object.
     * Mendukung DocumentDestruction individual maupun DocumentDestructionGroup.
     */
    public function generateSpreadsheet(
        DocumentDestruction|DocumentDestructionGroup $record,
        ?iterable $documents = null
    ): Spreadsheet|false {
        $templatePath = $this->getTemplatePath();

        if (! file_exists($templatePath)) {
            return false;
        }

        // Jika dokumen kustom diteruskan (misal untuk testing spesifik atau custom export)
        if ($documents !== null && $record instanceof DocumentDestruction) {
            return $this->generateSpreadsheetWithCustomDocuments($record, $documents);
        }

        if ($record instanceof DocumentDestructionGroup) {
            return $this->generateSpreadsheetForGroup($record);
        }

        // Jika record adalah DocumentDestruction dan sudah memiliki grup persetujuan
        if ($record->group) {
            return $this->generateSpreadsheetForGroup($record->group);
        }

        // Jika record belum terasosiasi grup tapi sudah disetujui, daftarkan ke grup
        if ($record->status === 'Disetujui' && $record->approved_at) {
            $group = $record->assignToApprovalGroup();
            if ($group) {
                return $this->generateSpreadsheetForGroup($group);
            }
        }

        // Fallback untuk single destruction request tanpa grup
        return $this->generateSpreadsheetForSingleDestruction($record);
    }

    /**
     * Generate spreadsheet untuk DocumentDestructionGroup (mengelompokkan dokumen berdasarkan tanggal persetujuan).
     */
    public function generateSpreadsheetForGroup(DocumentDestructionGroup $group): Spreadsheet|false
    {
        $templatePath = $this->getTemplatePath();

        if (! file_exists($templatePath)) {
            return false;
        }

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // 1. Header Date (H4)
        $approvalDateStr = $group->approval_date->format('d/m/Y');
        $sheet->setCellValue('H4', ": {$approvalDateStr}");

        $thinBorderArray = [
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THIN],
                'bottom' => ['borderStyle' => Border::BORDER_THIN],
                'left' => ['borderStyle' => Border::BORDER_THIN],
                'right' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ];

        // 2. Data Rows
        $items = [];
        $proposerNames = [];
        $approverNames = [];
        $submissionDates = [];
        $approvalDates = [];

        if ($group->isFinalized() && ! empty($group->snapshot_data)) {
            // Gunakan snapshot historis jika grup telah difinalisasi
            foreach ($group->snapshot_data as $snap) {
                $items[] = [
                    'document_number' => $snap['document_number'] ?? '',
                    'title' => $snap['title'] ?? '',
                    'category_name' => $snap['category_name'] ?? '',
                    'publication_date' => $snap['publication_date'] ?? '',
                    'revision_number' => $snap['revision_number'] ?? '',
                    'method' => $snap['method'] ?? '',
                ];

                if (! empty($snap['proposer_name'])) {
                    $proposerNames[] = $snap['proposer_name'];
                }
                if (! empty($snap['approver_name'])) {
                    $approverNames[] = $snap['approver_name'];
                }
                if (! empty($snap['submission_date'])) {
                    $submissionDates[] = $snap['submission_date'];
                }
                if (! empty($snap['approval_date'])) {
                    $approvalDates[] = $snap['approval_date'];
                }
            }
        } else {
            // Ambil data aktif dari relasi Eloquent
            $group->loadMissing([
                'destructions.document.category',
                'destructions.document.revisions',
                'destructions.proposer',
                'destructions.approver',
            ]);

            foreach ($group->destructions as $destruction) {
                $doc = $destruction->document;
                $latestRevision = null;
                if ($doc) {
                    $latestRevision = $doc->revisions
                        ->whereIn('status', ['Published', 'Terbit'])
                        ->sortByDesc(fn ($rev) => [$rev->revision_date?->timestamp ?? 0, $rev->id])
                        ->first()
                        ?? $doc->revisions
                            ->sortByDesc(fn ($rev) => [$rev->revision_date?->timestamp ?? 0, $rev->id])
                            ->first();
                }

                $publicationDate = '';
                if ($latestRevision?->revision_date) {
                    $publicationDate = $latestRevision->revision_date instanceof DateTimeInterface
                        ? $latestRevision->revision_date->format('d/m/Y')
                        : date('d/m/Y', strtotime((string) $latestRevision->revision_date));
                } elseif ($doc?->publication_year) {
                    $publicationDate = (string) $doc->publication_year;
                }

                $revisionNumber = (string) ($latestRevision?->revision_number ?? '');
                if ($revisionNumber !== '' && is_numeric($revisionNumber)) {
                    $revisionNumber = str_pad($revisionNumber, 2, '0', STR_PAD_LEFT);
                }

                $items[] = [
                    'document_number' => $doc?->document_number ?? '',
                    'title' => $doc?->title ?? '',
                    'category_name' => $doc?->category?->name ?? $doc?->category?->code ?? '',
                    'publication_date' => $publicationDate,
                    'revision_number' => $revisionNumber,
                    'method' => $destruction->method,
                ];

                if ($destruction->proposer?->name) {
                    $proposerNames[] = $destruction->proposer->name;
                }
                if ($destruction->approver?->name) {
                    $approverNames[] = $destruction->approver->name;
                }
                $submissionDates[] = ($destruction->created_at ?? now())->format('d/m/Y');
                $approvalDates[] = ($destruction->approved_at ?? $destruction->created_at ?? now())->format('d/m/Y');
            }
        }

        $count = count($items);
        $currentRow = 9;
        $nomor = 1;

        if ($count === 0) {
            $sheet->removeRow(10, 1);
        } elseif ($count === 1) {
            $this->writeRowValues($sheet, 9, 1, $items[0], $thinBorderArray);
            $sheet->removeRow(10, 1);
        } else {
            foreach ($items as $index => $item) {
                if ($index >= 2) {
                    $sheet->insertNewRowBefore($currentRow, 1);
                    $sheet->getRowDimension($currentRow)->setRowHeight(-1);
                }

                $this->writeRowValues($sheet, $currentRow, $nomor, $item, $thinBorderArray);
                $currentRow++;
                $nomor++;
            }
        }

        // 3. Dynamic Signature Section
        $nameRow = null;
        $dateRow = null;
        $highestRow = $sheet->getHighestRow();

        for ($r = 9; $r <= $highestRow; $r++) {
            $val = (string) $sheet->getCell("B{$r}")->getValue();
            if (str_starts_with(trim($val), 'Nama Pengendali Dokumen')) {
                $nameRow = $r;
            }
            if ($nameRow !== null && str_starts_with(trim($val), 'Tanggal')) {
                $dateRow = $r;
                break;
            }
        }

        $proposerText = implode(', ', array_unique($proposerNames));
        $approverText = implode(', ', array_unique($approverNames));
        $submissionText = ! empty($submissionDates) ? min($submissionDates) : now()->format('d/m/Y');
        $approvalText = $approvalDateStr;

        if ($nameRow !== null) {
            $sheet->setCellValue("B{$nameRow}", "Nama Pengendali Dokumen: {$proposerText}");
            $sheet->setCellValue("G{$nameRow}", "Nama Penanggung Jawab Mutu: {$approverText}");
        }

        if ($dateRow !== null) {
            $sheet->setCellValue("B{$dateRow}", "Tanggal : {$submissionText}");
            $sheet->setCellValue("G{$dateRow}", "Tanggal : {$approvalText}");
        }

        return $spreadsheet;
    }

    /**
     * Generate spreadsheet untuk single destruction request (fallback).
     */
    protected function generateSpreadsheetForSingleDestruction(DocumentDestruction $destruction): Spreadsheet|false
    {
        $destruction->loadMissing(['document.category', 'document.revisions', 'proposer', 'approver']);

        $item = [
            'document' => $destruction->document,
            'method' => $destruction->method,
        ];

        return $this->generateSpreadsheetWithCustomDocuments($destruction, [$item]);
    }

    /**
     * Generate spreadsheet dengan daftar dokumen kustom.
     */
    protected function generateSpreadsheetWithCustomDocuments(
        DocumentDestruction $destruction,
        iterable $documents
    ): Spreadsheet|false {
        $templatePath = $this->getTemplatePath();

        if (! file_exists($templatePath)) {
            return false;
        }

        $destruction->loadMissing(['proposer', 'approver']);

        $items = [];
        foreach ($documents as $entry) {
            if ($entry instanceof Document) {
                $entry->loadMissing(['category', 'revisions']);
                $items[] = [
                    'document' => $entry,
                    'method' => $destruction->method,
                ];
            } elseif (is_array($entry)) {
                $doc = $entry['document'] ?? null;
                if ($doc instanceof Document) {
                    $doc->loadMissing(['category', 'revisions']);
                }
                $items[] = [
                    'document' => $doc,
                    'method' => $entry['method'] ?? $destruction->method,
                ];
            }
        }

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // 1. Header Date
        $date = $destruction->approved_at ?? $destruction->created_at ?? now();
        $dateFormatted = $date->format('d/m/Y');
        $sheet->setCellValue('H4', ": {$dateFormatted}");

        $thinBorderArray = [
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THIN],
                'bottom' => ['borderStyle' => Border::BORDER_THIN],
                'left' => ['borderStyle' => Border::BORDER_THIN],
                'right' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ];

        // 2. Data Rows
        $count = count($items);
        $currentRow = 9;
        $nomor = 1;

        if ($count === 0) {
            $sheet->removeRow(10, 1);
        } elseif ($count === 1) {
            $this->fillDocumentRow($sheet, 9, 1, $items[0], $thinBorderArray);
            $sheet->removeRow(10, 1);
        } else {
            foreach ($items as $index => $item) {
                if ($index >= 2) {
                    $sheet->insertNewRowBefore($currentRow, 1);
                    $sheet->getRowDimension($currentRow)->setRowHeight(-1);
                }

                $this->fillDocumentRow($sheet, $currentRow, $nomor, $item, $thinBorderArray);
                $currentRow++;
                $nomor++;
            }
        }

        // 3. Signature
        $nameRow = null;
        $dateRow = null;
        $highestRow = $sheet->getHighestRow();

        for ($r = 9; $r <= $highestRow; $r++) {
            $val = (string) $sheet->getCell("B{$r}")->getValue();
            if (str_starts_with(trim($val), 'Nama Pengendali Dokumen')) {
                $nameRow = $r;
            }
            if ($nameRow !== null && str_starts_with(trim($val), 'Tanggal')) {
                $dateRow = $r;
                break;
            }
        }

        $proposerName = $destruction->proposer?->name ?? '';
        $approverName = $destruction->approver?->name ?? '';
        $submissionDate = ($destruction->created_at ?? now())->format('d/m/Y');
        $approvalDate = ($destruction->approved_at ?? $destruction->created_at ?? now())->format('d/m/Y');

        if ($nameRow !== null) {
            $sheet->setCellValue("B{$nameRow}", "Nama Pengendali Dokumen: {$proposerName}");
            $sheet->setCellValue("G{$nameRow}", "Nama Penanggung Jawab Mutu: {$approverName}");
        }

        if ($dateRow !== null) {
            $sheet->setCellValue("B{$dateRow}", "Tanggal : {$submissionDate}");
            $sheet->setCellValue("G{$dateRow}", "Tanggal : {$approvalDate}");
        }

        return $spreadsheet;
    }

    /**
     * Export a DocumentDestruction or DocumentDestructionGroup to an Excel file.
     *
     * @return string|false Path to the generated file in temp storage, or false if template is missing.
     */
    public function export(
        DocumentDestruction|DocumentDestructionGroup $record,
        ?iterable $documents = null
    ): string|false {
        $templatePath = $this->getTemplatePath();

        if (! file_exists($templatePath)) {
            return false;
        }

        $spreadsheet = $this->generateSpreadsheet($record, $documents);
        if ($spreadsheet === false) {
            return false;
        }

        $tempDir = storage_path('app/temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempPath = $tempDir . DIRECTORY_SEPARATOR . $this->getFilename($record);
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($tempPath);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $tempPath;
    }

    /**
     * Tuliskan nilai terpetakan ke satu baris tabel worksheet.
     */
    protected function writeRowValues(
        Worksheet $sheet,
        int $row,
        int $nomor,
        array $item,
        array $borderStyle
    ): void {
        $sheet->setCellValue("B{$row}", $nomor);
        $sheet->setCellValueExplicit("C{$row}", (string) ($item['document_number'] ?? ''), DataType::TYPE_STRING);
        $sheet->setCellValue("D{$row}", (string) ($item['title'] ?? ''));
        $sheet->setCellValue("E{$row}", (string) ($item['category_name'] ?? ''));
        $sheet->setCellValue("F{$row}", (string) ($item['publication_date'] ?? ''));
        $sheet->setCellValueExplicit("G{$row}", (string) ($item['revision_number'] ?? ''), DataType::TYPE_STRING);
        $sheet->setCellValue("H{$row}", (string) ($item['method'] ?? ''));

        foreach (range('B', 'H') as $col) {
            $sheet->getStyle("{$col}{$row}")->getFont()->setName('Arial')->setSize(8);
            $sheet->getStyle("{$col}{$row}")->applyFromArray($borderStyle);
        }

        $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    }

    /**
     * Helper untuk mapping data Dokumen ke baris tabel.
     */
    protected function fillDocumentRow(
        Worksheet $sheet,
        int $row,
        int $nomor,
        array $item,
        array $borderStyle
    ): void {
        $document = $item['document'] ?? null;
        $method = $item['method'] ?? '';

        $documentNumber = $document?->document_number ?? '';
        $title = $document?->title ?? '';
        $categoryName = $document?->category?->name ?? $document?->category?->code ?? '';

        $latestRevision = null;
        if ($document && $document->relationLoaded('revisions')) {
            $latestRevision = $document->revisions
                ->whereIn('status', ['Published', 'Terbit'])
                ->sortByDesc(fn ($rev) => [$rev->revision_date?->timestamp ?? 0, $rev->id])
                ->first()
                ?? $document->revisions
                    ->sortByDesc(fn ($rev) => [$rev->revision_date?->timestamp ?? 0, $rev->id])
                    ->first();
        } elseif ($document) {
            $latestRevision = $document->revisions()
                ->whereIn('status', ['Published', 'Terbit'])
                ->latest('revision_date')
                ->first()
                ?? $document->revisions()
                    ->latest('revision_date')
                    ->latest('id')
                    ->first();
        }

        $publicationDate = '';
        if ($latestRevision?->revision_date) {
            $publicationDate = $latestRevision->revision_date instanceof DateTimeInterface
                ? $latestRevision->revision_date->format('d/m/Y')
                : date('d/m/Y', strtotime((string) $latestRevision->revision_date));
        } elseif ($document?->publication_year) {
            $publicationDate = (string) $document->publication_year;
        }

        $revisionNumber = (string) ($latestRevision?->revision_number ?? '');
        if ($revisionNumber !== '' && is_numeric($revisionNumber)) {
            $revisionNumber = str_pad($revisionNumber, 2, '0', STR_PAD_LEFT);
        }

        $this->writeRowValues($sheet, $row, $nomor, [
            'document_number' => $documentNumber,
            'title' => $title,
            'category_name' => $categoryName,
            'publication_date' => $publicationDate,
            'revision_number' => $revisionNumber,
            'method' => $method,
        ], $borderStyle);
    }
}
