<?php

namespace App\Filament\Resources\Documents\Infolists;

use App\Models\DocumentDistributionLog;
use App\Services\PdfStamperService;
use Filament\Actions\Action as InfolistAction;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class RevisionActions
{
    /**
     * Tombol aksi yang ditampilkan per-revisi di tab Riwayat Revisi.
     * - Pratinjau: standalone (dengan logging)
     * - Word, Cetak Terkendali, Unduh Tidak Terkendali: dalam ActionGroup
     */
    public static function make(): array
    {
        return [
            self::pratinjauAction(),
            ActionGroup::make([
                self::unduhWordAction(),
                self::cetakTerkendaliAction(),
                self::unduhTidakTerkendaliAction(),
            ])
                ->icon('heroicon-m-ellipsis-vertical')
                ->tooltip('Distribusi')
                ->color('gray')
                ->visible(fn() => in_array(auth()->user()?->role, ['admin', 'manajemen'])),
        ];
    }

    private static function pratinjauAction(): InfolistAction
    {
        return InfolistAction::make('pratinjau')
            ->label('Pratinjau')
            ->icon('heroicon-m-eye')
            ->color('info')
            ->action(function ($record) {
                DocumentDistributionLog::create([
                    'document_revision_id' => $record->id,
                    'user_id' => auth()->id(),
                    'recipient_name' => auth()->user()->name,
                    'purpose' => 'Pratinjau dokumen via riwayat revisi',
                    'action' => 'Akses Digital (Sistem)',
                    'is_qr_access' => false,
                    'accessed_at' => now(),
                ]);

                return redirect()->away(route('secure.viewer', ['id' => $record->id]));
            });
    }

    private static function unduhWordAction(): InfolistAction
    {
        return InfolistAction::make('unduh_word')
            ->label('Unduh Word')
            ->icon('heroicon-m-document-text')
            ->color('info')
            ->visible(fn($record) => ! empty($record->word_file_path))
            ->action(function ($record) {
                $ext = pathinfo($record->word_file_path, PATHINFO_EXTENSION);

                return response()->download(
                    Storage::path($record->word_file_path),
                    "{$record->document->document_number} - {$record->document->title} - Rev {$record->revision_number}.{$ext}"
                );
            });
    }

    private static function cetakTerkendaliAction(): InfolistAction
    {
        return InfolistAction::make('cetak_terkendali')
            ->label('Cetak Terkendali')
            ->icon('heroicon-m-printer')
            ->color('success')
            ->visible(fn($record) => ! empty($record->file_path))
            ->form([
                TextInput::make('recipient_name')
                    ->label('Diberikan Kepada')
                    ->required()
                    ->maxLength(255),
                TextInput::make('purpose')
                    ->label('Tujuan/Keperluan')
                    ->required()
                    ->maxLength(255),
            ])
            ->modalHeading('Cetak Terkendali')
            ->action(function ($record, array $data) {
                if (empty($record->file_path) || ! Storage::exists($record->file_path)) {
                    Notification::make()
                        ->title('Gagal')
                        ->body('File PDF tidak tersedia untuk revisi ini.')
                        ->danger()
                        ->send();

                    return;
                }

                DocumentDistributionLog::create([
                    'document_revision_id' => $record->id,
                    'user_id' => auth()->id(),
                    'recipient_name' => $data['recipient_name'],
                    'purpose' => $data['purpose'],
                    'action' => 'Dicetak Terkendali',
                    'is_qr_access' => false,
                    'accessed_at' => now(),
                ]);

                $pdfContent = app(PdfStamperService::class)->stampControlled($record);
                $filename = "{$record->document->document_number} - {$record->document->title} (Rev {$record->revision_number}) - Controlled.pdf";

                return response()->streamDownload(function () use ($pdfContent) {
                    echo $pdfContent;
                }, $filename, [
                    'Content-Type' => 'application/pdf',
                ]);
            });
    }

    private static function unduhTidakTerkendaliAction(): InfolistAction
    {
        return InfolistAction::make('unduh_tidak_terkendali')
            ->label('Unduh Tidak Terkendali')
            ->icon('heroicon-m-arrow-down-tray')
            ->color('danger')
            ->visible(fn($record) => ! empty($record->file_path))
            ->form([
                TextInput::make('recipient_name')
                    ->label('Diberikan Kepada')
                    ->required()
                    ->maxLength(255),
                TextInput::make('purpose')
                    ->label('Tujuan/Keperluan')
                    ->required()
                    ->maxLength(255),
            ])
            ->modalHeading('Unduh Tidak Terkendali')
            ->action(function ($record, array $data) {
                try {
                    DocumentDistributionLog::create([
                        'document_revision_id' => $record->id,
                        'user_id' => auth()->id(),
                        'recipient_name' => $data['recipient_name'],
                        'purpose' => $data['purpose'],
                        'action' => 'Diunduh Tidak Terkendali',
                        'is_qr_access' => false,
                        'accessed_at' => now(),
                    ]);

                    $pdfContent = app(PdfStamperService::class)->stampUncontrolled($record);
                    $filename = "{$record->document->document_number} - {$record->document->title} (Rev {$record->revision_number}) - Uncontrolled.pdf";

                    return response()->streamDownload(function () use ($pdfContent) {
                        echo $pdfContent;
                    }, $filename, [
                        'Content-Type' => 'application/pdf',
                    ]);
                } catch (\RuntimeException $e) {
                    Notification::make()
                        ->title('Gagal')
                        ->body('Gagal membuat PDF tidak terkendali')
                        ->danger()
                        ->send();
                }
            });
    }
}
