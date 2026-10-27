<?php

namespace App\Services\DocumentSync\DTO;

readonly class ScanResult
{
    public const REASON_LIMIT_FILES = 'LIMIT_FILES_REACHED';
    public const REASON_LIMIT_SIZE = 'LIMIT_SIZE_REACHED';
    public const REASON_DIR_NOT_FOUND = 'DIRECTORY_NOT_FOUND';
    public const REASON_DIR_UNREADABLE = 'DIRECTORY_UNREADABLE';
    public const REASON_ERROR = 'SYSTEM_ERROR';

    /**
     * @param array<ScannedFileInfo> $files
     * @param array<string, string> $skippedFiles [relativePath => reason]
     * @param array<string> $errors
     */
    public function __construct(
        public array $files,
        public bool $isPartial,
        public int $totalFilesEncountered,
        public int $totalSizeBytes,
        public array $skippedFiles = [],
        public array $errors = [],
        public ?string $errorMessage = null,
        public ?string $partialReason = null,
    ) {}

    public function fileCount(): int
    {
        return count($this->files);
    }

    public function hasErrors(): bool
    {
        return ! empty($this->errors) || $this->errorMessage !== null;
    }

    public function toArray(): array
    {
        return [
            'file_count' => $this->fileCount(),
            'is_partial' => $this->isPartial,
            'partial_reason' => $this->partialReason,
            'total_files_encountered' => $this->totalFilesEncountered,
            'total_size_bytes' => $this->totalSizeBytes,
            'skipped_count' => count($this->skippedFiles),
            'has_errors' => $this->hasErrors(),
            'error_message' => $this->errorMessage,
        ];
    }
}
