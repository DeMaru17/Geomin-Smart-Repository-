<?php

namespace App\Filament\Resources\Documents\Infolists;

use App\Filament\Pages\CompareVersions;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Models\DocumentDistributionLog;
use App\Services\PdfStamperService;
use Filament\Actions\Action as InfolistAction;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class HeaderActions
{
    /**
     * Semua tombol aksi yang ditampilkan di header infolist (bagian kanan).
     * Untuk kategori Form ('F'): hanya Edit + Unduh Blanko.
     * Untuk kategori lain: full set (Buka, Cetak/Unduh, Lainnya).
     */
    public static function make(): array
    {
        return [
            // Kanan: utilitas (admin tools) — hidden untuk Form
            ActionGroup::make([
                self::editAction()
                    ->visible(fn() => in_array(auth()->user()?->role, ['admin', 'manajemen'])),
                self::bandingkanVersiAction(),
                self::cetakQrCodeAction(),
            ])
                ->label('Lainnya')
                ->icon('heroicon-m-ellipsis-vertical')
                ->color('gray')
                ->button()
                ->visible(fn($livewire) => $livewire->getRecord()->category?->code !== 'F'),

            // Tengah: cetak & unduh PDF — hidden untuk Form
            ActionGroup::make([
                self::cetakTerkendaliAction(),
                self::unduhTidakTerkendaliAction(),
            ])
                ->label('Cetak / Unduh')
                ->icon('heroicon-m-printer')
                ->color('success')
                ->button()
                ->visible(fn($livewire) => $livewire->getRecord()->category?->code !== 'F'),

            // Kiri: buka dokumen — hidden untuk Form
            self::bukaDokumenAction(),

            // Khusus Form: Edit
            self::editAction('edit_form')
                ->visible(fn($livewire) => in_array(auth()->user()?->role, ['admin', 'manajemen']) && $livewire->getRecord()->category?->code === 'F'),

            // Khusus Form: Unduh Blanko (revisi Published terbaru)
            self::unduhBlankoHeaderAction(),
        ];
    }

    private static function editAction(string $name = 'edit'): InfolistAction
    {
        return InfolistAction::make($name)
            ->label('Edit')
            ->icon('heroicon-m-pencil')
            ->color('primary')
            ->url(fn($record) => EditDocument::getUrl(['record' => $record]));
    }

    private static function cetakQrCodeAction(): InfolistAction
    {
        return InfolistAction::make('cetak_qr_code')
            ->label('Cetak QR Code')
            ->icon('heroicon-m-qr-code')
            ->color('gray')
            ->visible(fn() => auth()->user()?->role === 'admin')
            ->modalHeading('Cetak QR Code')
            ->modalContent(function ($record) {
                $url = config('app.url') . '/dokumen/aktif/' . $record->document_number;
                $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::size(250)->generate($url);
                $namaDokumen = $record->document_number . ' - ' . $record->title;

                $html = <<<HTML
                    <div>
                        <div id="qr-print-area" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1.5rem; padding: 2rem; text-align: center;">
                            <div style="display: block;">
                                {$qrSvg}
                            </div>
                            <div style="font-size: 1.25rem; color: #000; font-family: sans-serif; font-weight: bold; max-width: 320px; word-wrap: break-word; line-height: 1.4;">
                                {$namaDokumen}
                            </div>
                        </div>
                        <div style="text-align: center; margin-top: 1rem;">
                            <button type="button"
                                x-data
                                @click="
                                    let printWin = window.open('', '', 'width=800,height=800');
                                    printWin.document.write('<html><head><title>Cetak QR Code</title>');
                                    printWin.document.write('<style>body{margin:0;display:flex;justify-content:center;align-items:center;min-height:100vh;} svg{display:block;}</style>');
                                    printWin.document.write('</head><body>');
                                    printWin.document.write(document.getElementById('qr-print-area').outerHTML);
                                    printWin.document.write('</body></html>');
                                    printWin.document.close();
                                    printWin.focus();
                                    setTimeout(() => { printWin.print(); printWin.close(); }, 250);
                                "
                                class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-sm bg-primary-600 hover:bg-primary-500">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M7.875 1.5C6.839 1.5 6 2.34 6 3.375v2.99c-.426.053-.851.11-1.274.174-1.454.218-2.476 1.483-2.476 2.917v6.294a3 3 0 0 0 3 3h.27l-.092 1.086a1.875 1.875 0 0 0 1.87 2.04h9.405a1.875 1.875 0 0 0 1.87-2.04l-.093-1.086h.27a3 3 0 0 0 3-3V9.456c0-1.434-1.022-2.7-2.476-2.917A48.716 48.716 0 0 0 18 6.366V3.375c0-1.036-.84-1.875-1.875-1.875h-8.25ZM16.5 6.205v-2.83A.375.375 0 0 0 16.125 3h-8.25a.375.375 0 0 0-.375.375v2.83a49.353 49.353 0 0 1 9 0Zm-.217 8.265c.178.018.317.16.333.337l.526 5.568a.375.375 0 0 1-.374.41H7.232a.375.375 0 0 1-.374-.41l.526-5.568a.345.345 0 0 1 .333-.337 47.636 47.636 0 0 1 8.566 0Z" clip-rule="evenodd"/></svg>
                                Cetak
                            </button>
                        </div>
                    </div>
                    HTML;

                return new \Illuminate\Support\HtmlString($html);
            })
            ->modalFooterActions([])
            ->modalSubmitAction(false)
            ->action(fn() => null);
    }

    private static function bandingkanVersiAction(): InfolistAction
    {
        return InfolistAction::make('bandingkan')
            ->label('Bandingkan Versi')
            ->icon('heroicon-m-arrows-right-left')
            ->color('gray')
            ->url(fn($record) => CompareVersions::getUrl() . '?document_id=' . $record->id);
    }

    private static function bukaDokumenAction(): InfolistAction
    {
        return InfolistAction::make('buka_dokumen')
            ->label('Buka Dokumen')
            ->icon('heroicon-m-eye')
            ->color('info')
            ->visible(fn($record) => $record?->category?->code !== 'F')
            ->action(function ($record) {
                $revision = $record->revisions()
                    ->whereIn('status', ['Published', 'Approved'])
                    ->latest()
                    ->first() ?? $record->revisions()->latest()->first();

                if (! $revision) {
                    Notification::make()
                        ->title('Gagal')
                        ->body('Tidak ada revisi untuk dokumen ini.')
                        ->danger()
                        ->send();

                    return;
                }

                DocumentDistributionLog::create([
                    'document_revision_id' => $revision->id,
                    'user_id' => auth()->id(),
                    'recipient_name' => auth()->user()->name,
                    'purpose' => 'Buka dokumen via sistem',
                    'action' => 'Akses Digital (Sistem)',
                    'is_qr_access' => false,
                    'accessed_at' => now(),
                ]);

                return redirect()->away(route('secure.viewer', ['id' => $revision->id]));
            });
    }

    private static function cetakTerkendaliAction(): InfolistAction
    {
        return InfolistAction::make('cetak_terkendali')
            ->label('Cetak Terkendali')
            ->icon('heroicon-m-printer')
            ->color('success')
            ->visible(fn($record) => auth()->user()?->role === 'admin' && $record->revisions()->where('status', 'Published')->exists())
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
                $revision = $record->revisions()
                    ->where('status', 'Published')
                    ->latest()
                    ->first();

                if (! $revision) {
                    Notification::make()
                        ->title('Gagal')
                        ->body('Tidak ada revisi Published untuk dokumen ini.')
                        ->danger()
                        ->send();

                    return;
                }

                DocumentDistributionLog::create([
                    'document_revision_id' => $revision->id,
                    'user_id' => auth()->id(),
                    'recipient_name' => $data['recipient_name'],
                    'purpose' => $data['purpose'],
                    'action' => 'Salinan Terkendali (Cetak)',
                    'is_qr_access' => false,
                    'accessed_at' => now(),
                ]);

                $pdfContent = app(PdfStamperService::class)->stampControlled($revision);
                $filename = "{$revision->document->document_number} - {$revision->document->title} (Rev {$revision->revision_number}) - Controlled.pdf";

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
            ->visible(fn($record) => auth()->user()?->role === 'admin' && $record->revisions()->where('status', 'Published')->exists())
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
                $revision = $record->revisions()
                    ->where('status', 'Published')
                    ->latest()
                    ->first();

                if (! $revision) {
                    Notification::make()
                        ->title('Gagal')
                        ->body('Tidak ada revisi Published untuk dokumen ini.')
                        ->danger()
                        ->send();

                    return;
                }

                DocumentDistributionLog::create([
                    'document_revision_id' => $revision->id,
                    'user_id' => auth()->id(),
                    'recipient_name' => $data['recipient_name'],
                    'purpose' => $data['purpose'],
                    'action' => 'Salinan Tidak Terkendali (Unduh)',
                    'is_qr_access' => false,
                    'accessed_at' => now(),
                ]);

                $pdfContent = app(PdfStamperService::class)->stampUncontrolled($revision);
                $filename = "{$revision->document->document_number} - {$revision->document->title} (Rev {$revision->revision_number}) - Uncontrolled.pdf";

                return response()->streamDownload(function () use ($pdfContent) {
                    echo $pdfContent;
                }, $filename, [
                    'Content-Type' => 'application/pdf',
                ]);
            });
    }

    private static function unduhBlankoHeaderAction(): InfolistAction
    {
        return InfolistAction::make('unduh_blanko')
            ->label('Unduh Blanko')
            ->icon('heroicon-m-table-cells')
            ->color('success')
            ->button()
            ->visible(fn($record) => $record?->category?->code === 'F'
                && $record?->revisions()->where('status', 'Published')->exists())
            ->action(function ($record) {
                $revision = $record->revisions()
                    ->where('status', 'Published')
                    ->latest()
                    ->first();

                if (! $revision) {
                    Notification::make()
                        ->title('Gagal')
                        ->body('Tidak ada revisi Published untuk dokumen ini.')
                        ->danger()
                        ->send();

                    return;
                }

                $targetPath = null;

                if (! empty($revision->word_file_path) && Storage::exists($revision->word_file_path)) {
                    $targetPath = $revision->word_file_path;
                } elseif (! empty($revision->file_path) && Storage::exists($revision->file_path)) {
                    $targetPath = $revision->file_path;
                }

                if (! $targetPath) {
                    Notification::make()
                        ->title('Gagal')
                        ->body('Berkas master form tidak ditemukan di server.')
                        ->danger()
                        ->send();

                    return;
                }

                DocumentDistributionLog::create([
                    'document_revision_id' => $revision->id,
                    'user_id' => auth()->id(),
                    'recipient_name' => auth()->user()->name,
                    'purpose' => 'Mengunduh blanko form untuk isian',
                    'action' => 'Unduh Blanko (Excel)',
                    'is_qr_access' => false,
                    'accessed_at' => now(),
                ]);

                $extension = pathinfo($targetPath, PATHINFO_EXTENSION);
                $fileName = "{$record->document_number} - {$record->title} (Rev {$revision->revision_number}).{$extension}";

                return response()->download(Storage::path($targetPath), $fileName);
            });
    }
}
