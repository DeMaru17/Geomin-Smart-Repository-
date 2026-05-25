<?php

namespace App\Filament\Resources\QualityRecords\Pages;

use App\Filament\Resources\QualityRecords\QualityRecordResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQualityRecord extends CreateRecord
{
    protected static string $resource = QualityRecordResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Rekaman mutu berhasil dibuat';
    }
}
