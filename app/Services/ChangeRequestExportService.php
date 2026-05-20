<?php

namespace App\Services;

use App\Models\ChangeRequest;
use PhpOffice\PhpWord\TemplateProcessor;

class ChangeRequestExportService
{
    /**
     * Export a ChangeRequest to a Word document using the Form 204 template.
     *
     * @return string|false Path to the generated file, or false if template is missing.
     */
    public function export(ChangeRequest $changeRequest): string|false
    {
        $templatePath = storage_path('app/templates/273.106.204 Usulan permintaan atau perubahan dokumen.docx');

        if (! file_exists($templatePath)) {
            return false;
        }

        $changeRequest->loadMissing(['document', 'proposer', 'approver']);

        $templateProcessor = new TemplateProcessor($templatePath);

        // Replace scalar placeholders
        $latestRevisionNumber = '';
        if ($changeRequest->document) {
            $latestRevisionNumber = $changeRequest->document->revisions()
                ->whereIn('status', ['Published', 'Terbit'])
                ->orderByDesc('revision_number')
                ->value('revision_number') ?? '';
        }

        $templateProcessor->setValue('nomor_revisi', $latestRevisionNumber);
        $templateProcessor->setValue('document_number', $changeRequest->document?->document_number ?? '');
        $templateProcessor->setValue('document_title', $changeRequest->document?->title ?? '');
        $templateProcessor->setValue('proposal_date', $changeRequest->proposal_date?->format('d-m-Y') ?? '');
        $templateProcessor->setValue('proposer_name', $changeRequest->proposer?->name ?? '');
        $templateProcessor->setValue('reason', $changeRequest->reason ?? '');

        // Approval status checkmarks - centang pada status yang dipilih
        $status = $changeRequest->approval_status ?? '';
        $check = 'V';
        $templateProcessor->setValue('check_segera', $status === 'Segera dibuat revisi' ? $check : '');
        $templateProcessor->setValue('check_ditolak', $status === 'Ditolak' ? $check : '');
        $templateProcessor->setValue('check_ujicoba', $status === 'Di uji coba' ? $check : '');
        $templateProcessor->setValue('check_rtm', $status === 'Dibahas di RTM' ? $check : '');

        $templateProcessor->setValue('approval_notes', $changeRequest->approval_notes ?? '');
        $templateProcessor->setValue('effective_date', $changeRequest->effective_date?->format('d-m-Y') ?? '');
        $templateProcessor->setValue('approver_name', $changeRequest->approver?->name ?? '');

        // Handle proposed_changes table rows
        $proposedChanges = $changeRequest->proposed_changes ?? [];
        $rowCount = count($proposedChanges);

        if ($rowCount > 0) {
            $templateProcessor->cloneRow('halaman', $rowCount);

            foreach ($proposedChanges as $index => $change) {
                $rowNumber = $index + 1;
                $templateProcessor->setValue("halaman#{$rowNumber}", $change['halaman'] ?? '');
                $templateProcessor->setValue("item#{$rowNumber}", $change['item'] ?? '');
                $templateProcessor->setValue("detail_usulan#{$rowNumber}", $change['detail_usulan'] ?? '');
            }
        }

        // Save to temp file
        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $this->getFilename($changeRequest);
        $templateProcessor->saveAs($tempPath);

        return $tempPath;
    }

    /**
     * Generate the export filename for a ChangeRequest.
     */
    public function getFilename(ChangeRequest $changeRequest): string
    {
        $changeRequest->loadMissing('document');

        $documentNumber = $changeRequest->document?->document_number ?? 'unknown';
        $proposalDate = $changeRequest->proposal_date?->format('dmY') ?? 'nodate';

        return "Form-273.106.204-Usulan permintaan atau perubahan dokumen_{$documentNumber}_{$proposalDate}.docx";
    }
}
