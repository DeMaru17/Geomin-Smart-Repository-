<?php

namespace App\Services\DocumentSync\DTO;

readonly class ParsedFileInfo
{
    public function __construct(
        public string $filename,
        public string $relativePath,
        public string $extension,
        public int $sizeBytes,
        public string $hash,
        public ?string $documentNumber = null,
        public ?string $revisionNumber = null,
        public ?string $roleHint = null,
        public string $parseStatus = 'PARSED',
        public ?string $errorMessage = null,
    ) {}

    /**
     * Memeriksa apakah nomor dokumen dan nomor revisi berhasil diekstrak.
     */
    public function isParseComplete(): bool
    {
        return ! empty($this->documentNumber) && ! empty($this->revisionNumber);
    }

    /**
     * Konversi ke array untuk kemudahan serialisasi / logging.
     */
    public function toArray(): array
    {
        return [
            'filename' => $this->filename,
            'relative_path' => $this->relativePath,
            'extension' => $this->extension,
            'size_bytes' => $this->sizeBytes,
            'hash' => $this->hash,
            'document_number' => $this->documentNumber,
            'revision_number' => $this->revisionNumber,
            'role_hint' => $this->roleHint,
            'parse_status' => $this->parseStatus,
            'error_message' => $this->errorMessage,
            'is_parse_complete' => $this->isParseComplete(),
        ];
    }
}
