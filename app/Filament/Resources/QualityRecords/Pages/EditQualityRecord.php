<?php

namespace App\Filament\Resources\QualityRecords\Pages;

use App\Filament\Resources\QualityRecords\QualityRecordResource;
use Filament\Resources\Pages\EditRecord;

class EditQualityRecord extends EditRecord
{
    protected static string $resource = QualityRecordResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Rekaman mutu berhasil diperbarui';
    }
}
