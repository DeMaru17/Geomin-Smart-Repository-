<?php

namespace App\Services\DocumentSync\DTO;

use App\Models\DocumentImportItem;

readonly class InstallItemResult
{
    public const STATUS_SUCCESS = 'ATTACHED';
    public const STATUS_SKIPPED = 'SKIPPED';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_PROCESSING = 'PROCESSING';
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_SOURCE_MISSING = 'SOURCE_MISSING';
    public const STATUS_SOURCE_CHANGED = 'SOURCE_CHANGED';
    public const STATUS_TARGET_INVALID = 'TARGET_INVALID';
    public const STATUS_SLOT_OCCUPIED = 'SLOT_OCCUPIED';
    public const STATUS_ALREADY_ATTACHED = 'ALREADY_ATTACHED';

    public function __construct(
        public DocumentImportItem $item,
        public string $status,
        public bool $isSuccess,
        public ?string $installedPath = null,
        public ?string $errorMessage = null,
    ) {}

    public function toArray(): array
    {
        return [
            'item_id' => $this->item->id,
            'source_filename' => $this->item->source_filename,
            'status' => $this->status,
            'is_success' => $this->isSuccess,
            'installed_path' => $this->installedPath,
            'error_message' => $this->errorMessage,
        ];
    }
}
