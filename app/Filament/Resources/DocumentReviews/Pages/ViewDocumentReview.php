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
                    ])
                    ->live(),

                Select::make('destruction_method')
                    ->label('Metode Pemusnahan / Penarikan')
                    ->options([
                        'Penarikan Akses Digital' => 'Penarikan Akses Digital',
                        'Penghancuran Dokumen Fisik/Cetak' => 'Penghancuran Dokumen Fisik/Cetak',
                        'Lainnya' => 'Lainnya',
                    ])
                    ->default('Penarikan Akses Digital')
                    ->visible(fn ($get): bool => $get('evaluation_status') === 'Perlu dimusnahkan')
                    ->required(fn ($get): bool => $get('evaluation_status') === 'Perlu dimusnahkan'),

                Textarea::make('remarks')
                    ->label('Catatan')
                    ->maxLength(1000),
            ])
            ->action(function (array $data): void {
                $service = app(\App\Services\DocumentReviewService::class);
                $hasDestruction = false;

                \Illuminate\Support\Facades\DB::transaction(function () use ($data, $service, &$hasDestruction) {
                    DocumentReview::create([
                        'document_id' => $this->record->id,
                        'review_date' => $data['review_date'],
                        'reviewer_id' => auth()->id(),
                        'evaluation_status' => $data['evaluation_status'],
                        'remarks' => $data['remarks'] ?? null,
                    ]);

                    if ($data['evaluation_status'] === 'Perlu dimusnahkan') {
                        $destruction = $service->createDestructionRequestIfNeeded(
                            document: $this->record,
                            proposerId: auth()->id(),
                            reason: $data['remarks'] ?? null,
                            method: $data['destruction_method'] ?? 'Penarikan Akses Digital'
                        );

                        if ($destruction && $destruction->wasRecentlyCreated) {
                            $hasDestruction = true;
                        }
                    }
                });

                $title = 'Kaji ulang berhasil dicatat';
                $body = null;
                if ($hasDestruction) {
                    $body = 'Pengajuan pemusnahan dokumen otomatis dibuat dan masuk ke antrean persetujuan (Pending).';
                }

                $notification = Notification::make()
                    ->title($title)
                    ->success();

                if ($body) {
                    $notification->body($body);
                }

                $notification->send();
            });
    }
}
