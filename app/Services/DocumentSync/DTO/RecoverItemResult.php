<?php

namespace App\Services\DocumentSync\DTO;

use App\Models\DocumentImportItem;

readonly class RecoverItemResult
{
    public const ACTION_CONFIRMED_ATTACHED = 'CONFIRMED_ATTACHED';
    public const ACTION_RESET_FAILED = 'RESET_FAILED';
    public const ACTION_SKIPPED_ACTIVE = 'SKIPPED_ACTIVE';
    public const ACTION_NOT_STUCK = 'NOT_STUCK';

    public function __construct(
        public DocumentImportItem $item,
        public string $action,
        public bool $wasRecovered,
        public ?string $message = null,
    ) {}

    public function toArray(): array
    {
        return [
            'item_id' => $this->item->id,
            'action' => $this->action,
            'was_recovered' => $this->wasRecovered,
            'message' => $this->message,
        ];
    }
}
