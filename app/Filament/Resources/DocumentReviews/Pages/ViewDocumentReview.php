<?php

namespace App\Filament\Resources\DocumentReviews\Pages;

use App\Filament\Resources\DocumentReviews\DocumentReviewResource;
use App\Models\DocumentReview;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewDocumentReview extends ViewRecord
{
    protected static string $resource = DocumentReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getCatatKajiUlangAction(),
        ];
    }

    protected function getCatatKajiUlangAction(): Action
    {
        return Action::make('catat_kaji_ulang')
            ->label('+ Catat Kaji Ulang')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('primary')
            ->visible(fn(): bool => in_array(auth()->user()?->role, ['admin', 'manajemen']))
            ->before(function (): void {
                abort_unless(in_array(auth()->user()?->role, ['admin', 'manajemen']), 403);
            })
            ->form([
                TextInput::make('document_id')
                    ->label('Dokumen')
                    ->default(fn() => $this->record->document_number . ' - ' . $this->record->title)
                    ->disabled()
                    ->dehydrated(false),

                DatePicker::make('review_date')
                    ->label('Tanggal Kaji Ulang')
                    ->required()
                    ->default(now()),

                Select::make('evaluation_status')
                    ->label('Status Evaluasi')
                    ->required()
                    ->options([
                        'Perlu direvisi' => 'Perlu direvisi',
                        'Perlu diperbaharui' => 'Perlu diperbaharui',
                        'Perlu ditarik' => 'Perlu ditarik',
                        'Perlu dimusnahkan' => 'Perlu dimusnahkan',
                        'Dipertahankan' => 'Dipertahankan',
                    ]),

                Textarea::make('remarks')
                    ->label('Catatan')
                    ->maxLength(1000),
            ])
            ->action(function (array $data): void {
                DocumentReview::create([
                    'document_id' => $this->record->id,
                    'review_date' => $data['review_date'],
                    'reviewer_id' => auth()->id(),
                    'evaluation_status' => $data['evaluation_status'],
                    'remarks' => $data['remarks'] ?? null,
                ]);

                Notification::make()
                    ->title('Kaji ulang berhasil dicatat')
                    ->success()
                    ->send();
            });
    }
}
