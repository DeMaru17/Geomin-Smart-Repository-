<?php

namespace App\Services\DocumentSync\DTO;

readonly class MatchResult
{
    public function __construct(
        public ParsedFileInfo $parsedInfo,
        public ?int $documentId = null,
        public ?int $revisionId = null,
        public ?string $documentNumber = null,
        public ?string $revisionNumber = null,
        public ?string $categoryCode = null,
        public ?string $targetSlot = null,
        public string $matchStatus = 'PENDING',
        public string $slotCurrentStatus = 'UNKNOWN',
        public string $decision = 'PENDING',
        public ?string $errorMessage = null,
    ) {}

    /**
     * Memeriksa apakah hasil pencocokan memenuhi syarat untuk dipilih/dipasang.
     */
    public function isEligibleForSelection(): bool
    {
        return in_array($this->decision, ['READY_TO_ATTACH', 'PLACEHOLDER_REPLACEABLE', 'MANUAL_RESOLVED'])
            && ! in_array($this->slotCurrentStatus, ['SLOT_OCCUPIED_LOCKED', 'SLOT_STORAGE_UNREACHABLE']);
    }

    /**
     * Konversi ke array untuk pemetaan ke tabel staging document_import_items.
     */
    public function toArray(): array
    {
        return [
            'source_filename' => $this->parsedInfo->filename,
            'source_relative_path' => $this->parsedInfo->relativePath,
            'file_extension' => $this->parsedInfo->extension,
            'file_size_bytes' => $this->parsedInfo->sizeBytes,
            'file_hash' => $this->parsedInfo->hash,
            'parsed_document_number' => $this->parsedInfo->documentNumber,
            'parsed_revision_number' => $this->parsedInfo->revisionNumber,
            'role_hint' => $this->parsedInfo->roleHint,
            'matched_document_id' => $this->documentId,
            'matched_revision_id' => $this->revisionId,
            'target_slot' => $this->targetSlot,
            'slot_current_status' => $this->slotCurrentStatus,
            'match_status' => $this->matchStatus,
            'decision' => $this->decision,
            'is_selected' => false, // Default seleksi selalu false
            'error_message' => $this->errorMessage,
        ];
    }
}
