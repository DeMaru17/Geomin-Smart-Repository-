<?php

namespace App\Filament\Resources\ChangeRequests\Pages;

use App\Filament\Resources\ChangeRequests\ChangeRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateChangeRequest extends CreateRecord
{
    protected static string $resource = ChangeRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['proposer_id'] = auth()->id();
        $data['approval_status'] = 'Pending';

        return $data;
    }
}
