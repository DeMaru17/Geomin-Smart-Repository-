<?php

namespace App\Services;

use App\Models\DocumentReview;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentReviewExportService
{
    /**
     * Mapping nama bulan Indonesia berdasarkan angka (1-12).
     */
    protected array $namaBulan = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    /**
     * Mapping evaluation_status ke kolom checkmark (G-K).
     */
    protected array $statusColumnMap = [
        'Perlu direvisi' => 'G',
        'Perlu diperbaharui' => 'H',
        'Perlu ditarik' => 'I',
        'Perlu dimusnahkan' => 'J',
        'Dipertahankan' => 'K',
    ];

    /**
     * Export data kaji ulang ke Excel berdasarkan parameter.
     *
     * Menggunakan chunked query untuk efisiensi memori meskipun volume
     * data tahunan relatif kecil. PhpSpreadsheet insertNewRowBefore
     * dilakukan sequential per record.
     *
     * @param  string|null  $periodeBulan  - null/'all' untuk semua bulan, atau angka 1-12
     * @param  int  $periodeTahun
     * @param  string  $disiapkanOleh
     * @param  string  $diperiksaOleh
     * @param  string  $disetujuiOleh
     * @return StreamedResponse|false - false jika template tidak ditemukan
     */
    public function export(
        ?string $periodeBulan,
        int $periodeTahun,
        string $disiapkanOleh,
        string $diperiksaOleh,
        string $disetujuiOleh,
    ): StreamedResponse|false {
        $templatePath = storage_path('app/templates/273.106.205 Evaluasi Kaji Ulang Dokumen Rev. 01.xlsx');

        if (! file_exists($templatePath)) {
            return false;
        }

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Fill header cells
        $bulanLabel = $this->getBulanLabel($periodeBulan);
        $sheet->setCellValue('C5', $bulanLabel);
        $sheet->setCellValue('C6', $periodeTahun);

        // Collect all reviews for the period
        $reviews = collect();

        $query = DocumentReview::with(['document.category', 'document.revisions'])
            ->whereYear('review_date', $periodeTahun);

        if ($periodeBulan !== null && $periodeBulan !== 'all') {
            $query->whereMonth('review_date', (int) $periodeBulan);
        }

        $query->chunk(100, function ($chunk) use (&$reviews) {
            $reviews = $reviews->merge($chunk);
        });

        if ($reviews->isEmpty()) {
            return false;
        }

        // Fill data rows starting at Row 12
        $startRow = 12;
        $currentRow = $startRow;
        $nomor = 1;

        foreach ($reviews as $index => $review) {
            // Insert new row for additional records (not the first one)
            if ($index > 0) {
                $sheet->insertNewRowBefore($currentRow, 1);
            }

            // Get document data
            $document = $review->document;
            $documentNumber = $document?->document_number ?? '';
            $title = $document?->title ?? '';
            $categoryName = $document?->category?->name ?? '';

            // Get latest Published/Terbit revision
            $latestRevision = $document?->revisions
                ->whereIn('status', ['Published', 'Terbit'])
                ->sortByDesc('revision_date')
                ->first();

            $revisionNumber = $latestRevision?->revision_number ?? '';
            $revisionDate = $latestRevision?->revision_date
                ? $latestRevision->revision_date->format('d/m/Y')
                : '';

            // Fill row data
            $sheet->setCellValue("A{$currentRow}", $nomor);
            $sheet->setCellValue("B{$currentRow}", $documentNumber);
            $sheet->setCellValue("C{$currentRow}", $title);
            $sheet->setCellValue("D{$currentRow}", $categoryName);
            $sheet->setCellValue("E{$currentRow}", $revisionNumber);
            $sheet->setCellValue("F{$currentRow}", $revisionDate);

            // Checkmark based on evaluation_status (G-K)
            $this->fillCheckmark($sheet, $currentRow, $review->evaluation_status);

            // Remarks
            $sheet->setCellValue("L{$currentRow}", $review->remarks ?? '');

            $currentRow++;
            $nomor++;
        }

        // Fill signature cells (dynamically positioned after data rows)
        // Signature row is positioned after the last data row with some offset
        $signatureRow = $currentRow + 4;
        $sheet->setCellValue("K{$signatureRow}", $disiapkanOleh);
        $signatureRow++;
        $sheet->setCellValue("K{$signatureRow}", $diperiksaOleh);
        $signatureRow++;
        $sheet->setCellValue("K{$signatureRow}", $disetujuiOleh);

        // Return StreamedResponse for download
        $filename = $this->getFilename($periodeBulan, $periodeTahun);

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    /**
     * Generate nama file ekspor.
     *
     * Format: Form-273.106.205_Evaluasi_Kaji_Ulang_Dokumen_[bulan]-[tahun].xlsx
     */
    public function getFilename(?string $periodeBulan, int $periodeTahun): string
    {
        $bulanPart = $this->getFilenameBulanPart($periodeBulan);

        return "Form-273.106.205_Evaluasi_Kaji_Ulang_Dokumen_{$bulanPart}-{$periodeTahun}.xlsx";
    }

    /**
     * Get label bulan untuk header cell.
     */
    protected function getBulanLabel(?string $periodeBulan): string
    {
        if ($periodeBulan === null || $periodeBulan === 'all') {
            return 'Semua Bulan';
        }

        $bulanInt = (int) $periodeBulan;

        return $this->namaBulan[$bulanInt] ?? 'Semua Bulan';
    }

    /**
     * Get bagian bulan untuk nama file.
     */
    protected function getFilenameBulanPart(?string $periodeBulan): string
    {
        if ($periodeBulan === null || $periodeBulan === 'all') {
            return 'Semua-Bulan';
        }

        $bulanInt = (int) $periodeBulan;

        return $this->namaBulan[$bulanInt] ?? 'Semua-Bulan';
    }

    /**
     * Fill checkmark 'V' pada kolom yang sesuai berdasarkan evaluation_status.
     */
    protected function fillCheckmark(Worksheet $sheet, int $row, string $evaluationStatus): void
    {
        // Clear all checkmark columns first
        foreach (['G', 'H', 'I', 'J', 'K'] as $col) {
            $sheet->setCellValue("{$col}{$row}", '');
        }

        // Set checkmark on the correct column
        if (isset($this->statusColumnMap[$evaluationStatus])) {
            $col = $this->statusColumnMap[$evaluationStatus];
            $sheet->setCellValue("{$col}{$row}", 'V');
        }
    }
}
