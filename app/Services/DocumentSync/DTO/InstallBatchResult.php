<?php

namespace App\Services\DocumentSync\DTO;

use App\Models\DocumentImportBatch;

readonly class InstallBatchResult
{
    /**
     * @param array<InstallItemResult> $itemResults
     */
    public function __construct(
        public DocumentImportBatch $batch,
        public int $totalProcessed,
        public int $totalInstalled,
        public int $totalSkipped,
        public int $totalFailed,
        public array $itemResults = [],
        public ?string $errorMessage = null,
    ) {}

    public function toArray(): array
    {
        return [
            'batch_id' => $this->batch->id,
            'batch_number' => $this->batch->batch_number,
            'total_processed' => $this->totalProcessed,
            'total_installed' => $this->totalInstalled,
            'total_skipped' => $this->totalSkipped,
            'total_failed' => $this->totalFailed,
            'has_errors' => $this->totalFailed > 0 || $this->errorMessage !== null,
            'error_message' => $this->errorMessage,
        ];
    }
}
