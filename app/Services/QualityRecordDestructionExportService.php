<?php

namespace App\Services;

use App\Models\QualityRecord;
use App\Models\QualityRecordDestructionGroup;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class QualityRecordDestructionExportService
{
    /**
     * Path ke template Excel resmi Form 273.106.208.
     */
    public function getTemplatePath(): string
    {
        return storage_path('app/templates/273.106.208 Berita Acara Pemusnahan Rekaman Rev00.xlsx');
    }

    /**
     * Generate the export filename for a QualityRecordDestructionGroup.
     *
     * Format: BAPR_[sanitized-number].xlsx
     */
    public function getFilename(QualityRecordDestructionGroup $group): string
    {
        $sanitizedNumber = str_replace(['/', '\\'], '-', $group->bapr_number);

        return "BAPR_{$sanitizedNumber}.xlsx";
    }

    /**
     * Muat template Excel resmi, isi data BAPR, dan kembalikan Spreadsheet object.
     */
    public function generateSpreadsheet(QualityRecordDestructionGroup $group): Spreadsheet|false
    {
        $templatePath = $this->getTemplatePath();

        if (! file_exists($templatePath)) {
            return false;
        }

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // 1. Header Date (H4)
        $destructionDateStr = $group->destruction_date->format('d/m/Y');
        $sheet->setCellValue('H4', ": {$destructionDateStr}");

        $thinBorderArray = [
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THIN],
                'bottom' => ['borderStyle' => Border::BORDER_THIN],
                'left' => ['borderStyle' => Border::BORDER_THIN],
                'right' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ];

        // 2. Data Items
        $items = [];
        $destroyedByNames = [];
        $acknowledgedByNames = [];

        if ($group->isFinalized() && ! empty($group->snapshot_data)) {
            foreach ($group->snapshot_data as $snap) {
                $items[] = [
                    'no_rekaman' => $snap['document_number'] ?? $snap['record_name'] ?? '',
                    'periode' => $snap['record_date'] ?? '',
                    'diarsipkan' => $snap['archived_date'] ?? '',
                    'masa_simpan' => $snap['retention_period'] ?? '',
                    'tgl_dimusnahkan' => $snap['destruction_date'] ?? $destructionDateStr,
                    'cara' => $snap['method'] ?? $group->method ?? 'Dihapus dari server & backup',
                ];

                if (! empty($snap['destroyed_by_name'])) {
                    $destroyedByNames[] = $snap['destroyed_by_name'];
                }
                if (! empty($snap['acknowledged_by_name'])) {
                    $acknowledgedByNames[] = $snap['acknowledged_by_name'];
                }
            }
        } else {
            $group->loadMissing(['records.document', 'records.destructionOperator', 'destroyedBy', 'acknowledgedBy']);

            foreach ($group->records as $record) {
                $doc = $record->document;
                $retentionMonths = $doc?->retention_period_months ?? QualityRecord::DEFAULT_RETENTION_MONTHS;

                $items[] = [
                    'no_rekaman' => $doc?->document_number ?? $record->record_name,
                    'periode' => $record->record_date?->format('d/m/Y') ?? '',
                    'diarsipkan' => $record->created_at?->format('d/m/Y') ?? '',
                    'masa_simpan' => "{$retentionMonths} Bulan",
                    'tgl_dimusnahkan' => ($record->destruction_date ?? $group->destruction_date)->format('d/m/Y'),
                    'cara' => $record->destruction_method ?? $group->method ?? 'Dihapus dari server & backup',
                ];

                if ($record->destructionOperator?->name) {
                    $destroyedByNames[] = $record->destructionOperator->name;
                }
            }

            if ($group->destroyedBy?->name) {
                $destroyedByNames[] = $group->destroyedBy->name;
            }
            if ($group->acknowledgedBy?->name) {
                $acknowledgedByNames[] = $group->acknowledgedBy->name;
            }
        }

        // 3. Populate Rows
        $count = count($items);
        $defaultRows = 4; // Rows 9, 10, 11, 12 in the template

        if ($count > $defaultRows) {
            $extra = $count - $defaultRows;
            $sheet->insertNewRowBefore(13, $extra);
        } elseif ($count < $defaultRows && $count > 0) {
            $removeStart = 9 + $count;
            $removeNum = $defaultRows - $count;
            $sheet->removeRow($removeStart, $removeNum);
        } elseif ($count === 0) {
            $sheet->removeRow(10, 3);
        }

        $currentRow = 9;
        foreach ($items as $idx => $item) {
            $nomor = $idx + 1;
            $this->writeRowValues($sheet, $currentRow, $nomor, $item, $thinBorderArray);
            $currentRow++;
        }

        // 4. Dynamic Signature Block
        $nameRow = null;
        $dateRow = null;
        $highestRow = $sheet->getHighestRow();

        for ($r = 9; $r <= $highestRow; $r++) {
            $val = (string) $sheet->getCell("B{$r}")->getValue();
            if (str_starts_with(trim($val), 'Nama Pengendali Rekaman')) {
                $nameRow = $r;
            }
            if ($nameRow !== null && str_starts_with(trim($val), 'Tanggal')) {
                $dateRow = $r;
                break;
            }
        }

        $destroyedByText = implode(', ', array_unique(array_filter($destroyedByNames)));
        $acknowledgedByText = implode(', ', array_unique(array_filter($acknowledgedByNames)));

        if ($nameRow !== null) {
            $sheet->setCellValue("B{$nameRow}", "Nama Pengendali Rekaman: {$destroyedByText}");
            $sheet->setCellValue("G{$nameRow}", "Nama Penanggung Jawab Mutu: {$acknowledgedByText}");
        }

        if ($dateRow !== null) {
            $sheet->setCellValue("B{$dateRow}", "Tanggal : {$destructionDateStr}");
            $sheet->setCellValue("G{$dateRow}", "Tanggal : {$destructionDateStr}");
        }

        return $spreadsheet;
    }

    /**
     * Tuliskan nilai ke satu baris tabel worksheet.
     */
    protected function writeRowValues(
        Worksheet $sheet,
        int $row,
        int $nomor,
        array $item,
        array $borderStyle
    ): void {
        $sheet->setCellValue("B{$row}", $nomor);
        $sheet->setCellValueExplicit("C{$row}", (string) ($item['no_rekaman'] ?? ''), DataType::TYPE_STRING);
        $sheet->setCellValue("D{$row}", (string) ($item['periode'] ?? ''));
        $sheet->setCellValue("E{$row}", (string) ($item['diarsipkan'] ?? ''));
        $sheet->setCellValue("F{$row}", (string) ($item['masa_simpan'] ?? ''));
        $sheet->setCellValue("G{$row}", (string) ($item['tgl_dimusnahkan'] ?? ''));
        $sheet->setCellValue("H{$row}", (string) ($item['cara'] ?? ''));

        foreach (range('B', 'H') as $col) {
            $sheet->getStyle("{$col}{$row}")->getFont()->setName('Arial')->setSize(8);
            $sheet->getStyle("{$col}{$row}")->applyFromArray($borderStyle);
        }

        $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    }

    /**
     * Export a QualityRecordDestructionGroup to an Excel file.
     *
     * @return string|false Path to the generated file in temp storage, or false if template is missing.
     */
    public function export(QualityRecordDestructionGroup $group): string|false
    {
        $templatePath = $this->getTemplatePath();

        if (! file_exists($templatePath)) {
            return false;
        }

        $spreadsheet = $this->generateSpreadsheet($group);
        if ($spreadsheet === false) {
            return false;
        }

        $tempDir = storage_path('app/temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempPath = $tempDir . DIRECTORY_SEPARATOR . $this->getFilename($group);
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($tempPath);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $tempPath;
    }
}
