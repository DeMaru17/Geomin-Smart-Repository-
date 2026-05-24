<?php

namespace App\Services;

use App\Models\DocumentDestruction;
use PhpOffice\PhpWord\TemplateProcessor;

class DocumentDestructionExportService
{
    /**
     * Export a DocumentDestruction (BAPD) to a Word document using the template.
     *
     * @return string|false Path to the generated file, or false if template is missing.
     */
    public function export(DocumentDestruction $destruction): string|false
    {
        $templatePath = storage_path('app/templates/Template bapd.docx');

        if (! file_exists($templatePath)) {
            return false;
        }

        $destruction->loadMissing(['document', 'proposer', 'approver']);

        $templateProcessor = new TemplateProcessor($templatePath);

        // Replace placeholders with BAPD data
        $templateProcessor->setValue('nomor_bapd', $destruction->bapd_number ?? '');
        $templateProcessor->setValue('nomor_dokumen', $destruction->document?->document_number ?? '');
        $templateProcessor->setValue('judul_dokumen', $destruction->document?->title ?? '');
        $templateProcessor->setValue('alasan', $destruction->reason ?? '');
        $templateProcessor->setValue('metode', $destruction->method ?? '');
        $templateProcessor->setValue('nama_pengusul', $destruction->proposer?->name ?? '');
        $templateProcessor->setValue('nama_penyetuju', $destruction->approver?->name ?? '');
        $templateProcessor->setValue('tanggal_persetujuan', $destruction->approved_at?->format('d-m-Y') ?? '');

        // Save to temp directory
        $tempDir = storage_path('app/temp');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempPath = $tempDir . DIRECTORY_SEPARATOR . $this->getFilename($destruction);
        $templateProcessor->saveAs($tempPath);

        return $tempPath;
    }

    /**
     * Generate the export filename for a DocumentDestruction.
     *
     * Format: BAPD_[sanitized-number]_[ddmmYYYY].docx
     * Sanitizes / and \ to -
     */
    public function getFilename(DocumentDestruction $destruction): string
    {
        $sanitizedNumber = str_replace(['/', '\\'], '-', $destruction->bapd_number ?? 'unknown');
        // $date = $destruction->approved_at?->format('dmY') ?? now()->format('dmY');

        return "BAPD_{$sanitizedNumber}.docx";
    }
}
