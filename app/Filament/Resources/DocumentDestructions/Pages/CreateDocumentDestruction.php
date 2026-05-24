<?php

namespace App\Filament\Resources\DocumentDestructions\Pages;

use App\Filament\Resources\DocumentDestructions\DocumentDestructionResource;
use App\Models\Document;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateDocumentDestruction extends CreateRecord
{
    protected static string $resource = DocumentDestructionResource::class;

    protected ?int $prefillDocumentId = null;

    protected ?string $prefillReason = null;

    public function mount(): void
    {
        parent::mount();

        $documentId = request()->query('document_id');
        $reason = request()->query('reason');

        $fillData = [];

        if ($documentId && Document::where('id', $documentId)->exists()) {
            $this->prefillDocumentId = (int) $documentId;
            $fillData['document_id'] = $this->prefillDocumentId;
        }

        if ($reason) {
            $this->prefillReason = $reason;
            $fillData['reason'] = $this->prefillReason;
        }

        if (! empty($fillData)) {
            $fillData['proposer_id'] = auth()->id();
            $fillData['status'] = 'Pending';
            $this->form->fill($fillData);
        }
    }

    public function getDocumentIdDisabled(): bool
    {
        return $this->prefillDocumentId !== null;
    }

    public function getReasonDisabled(): bool
    {
        return $this->prefillReason !== null;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Pengajuan pemusnahan berhasil dibuat');
    }
}
