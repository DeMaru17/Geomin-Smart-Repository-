<?php

namespace App\Filament\Resources\DocumentImportBatches\Pages;

use App\Filament\Resources\DocumentImportBatches\DocumentImportBatchResource;
use App\Filament\Resources\DocumentImportBatches\RelationManagers\ItemsRelationManager;
use App\Services\DocumentSync\DocumentInstallerService;
use App\Services\DocumentSync\DTO\RecoverItemResult;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewDocumentImportBatch extends ViewRecord
{
    protected static string $resource = DocumentImportBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('install_all_selected')
                ->label('Pasang Semua Berkas Terpilih')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('success')
                ->authorize(fn() => in_array(auth()->user()?->role, ['admin', 'manajemen'], true))
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Pemasangan Berkas Terpilih')
                ->modalDescription('Sistem akan mengeksekusi pemasangan ke seluruh berkas yang telah ditandai terpilih (is_selected) dan memenuhi syarat pemasangan.')
                ->action(function (DocumentInstallerService $installer) {
                    abort_unless(in_array(auth()->user()?->role, ['admin', 'manajemen'], true), 403, 'Aksi ini hanya dapat dilakukan oleh Admin atau Manajemen.');

                    $batch = $this->getRecord();
                    $selectedItems = $batch->items()
                        ->where('is_selected', true)
                        ->where('install_status', '!=', \App\Services\DocumentSync\DTO\InstallItemResult::STATUS_SUCCESS)
                        ->whereIn('decision', ['READY_TO_ATTACH', 'PLACEHOLDER_REPLACEABLE', 'MANUAL_RESOLVED'])
                        ->get();

                    if ($selectedItems->isEmpty()) {
                        Notification::make()
                            ->title('Tidak Ada Berkas Terpilih')
                            ->body('Tidak ditemukan berkas yang ditandai terpilih dan memenuhi syarat untuk dipasang.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $success = 0;
                    $failed = 0;

                    foreach ($selectedItems as $item) {
                        $res = $installer->installItem($item);
                        if ($res->isSuccess) {
                            $success++;
                        } else {
                            $failed++;
                        }
                    }

                    // Update statistik batch
                    $batch->refresh();
                    $batch->total_installed = $batch->items()->where('install_status', \App\Services\DocumentSync\DTO\InstallItemResult::STATUS_SUCCESS)->count();
                    $batch->total_failed = $batch->items()->whereNotIn('install_status', [\App\Services\DocumentSync\DTO\InstallItemResult::STATUS_PENDING, \App\Services\DocumentSync\DTO\InstallItemResult::STATUS_PROCESSING, \App\Services\DocumentSync\DTO\InstallItemResult::STATUS_SUCCESS, 'SKIPPED'])->count();

                    if ($batch->items()->whereNotIn('install_status', [\App\Services\DocumentSync\DTO\InstallItemResult::STATUS_SUCCESS, 'SKIPPED'])->count() === 0) {
                        $batch->status = 'Completed';
                        $batch->completed_at = now();
                    }
                    $batch->save();

                    Notification::make()
                        ->title('Pemasangan Selesai')
                        ->body("Hasil pemasangan batch: {$success} berhasil, {$failed} gagal.")
                        ->color($failed > 0 ? 'warning' : 'success')
                        ->send();
                }),

            Action::make('recover_batch')
                ->label('Pulihkan Item Terjebak')
                ->icon('heroicon-m-shield-check')
                ->color('warning')
                ->authorize(fn() => in_array(auth()->user()?->role, ['admin', 'manajemen'], true))
                ->visible(fn() => $this->getRecord()->items()->where('install_status', \App\Services\DocumentSync\DTO\InstallItemResult::STATUS_PROCESSING)->exists())
                ->requiresConfirmation()
                ->modalHeading('Pemulihan Item Berstatus PROCESSING')
                ->modalDescription('Mengecek integritas berkas dan database untuk seluruh item yang tertinggal dalam status PROCESSING pada batch ini. Proses aktif (< 5 menit) tidak akan diambil alih.')
                ->action(function (DocumentInstallerService $installer) {
                    abort_unless(in_array(auth()->user()?->role, ['admin', 'manajemen'], true), 403, 'Aksi ini hanya dapat dilakukan oleh Admin atau Manajemen.');

                    $batch = $this->getRecord();
                    $results = $installer->recoverStuckItemsInBatch($batch, staleSeconds: 300, force: false);

                    $confirmed = 0;
                    $reset = 0;
                    $skipped = 0;

                    foreach ($results as $res) {
                        if ($res->action === RecoverItemResult::ACTION_CONFIRMED_ATTACHED) {
                            $confirmed++;
                        } elseif ($res->action === RecoverItemResult::ACTION_RESET_FAILED) {
                            $reset++;
                        } elseif ($res->action === RecoverItemResult::ACTION_SKIPPED_ACTIVE) {
                            $skipped++;
                        }
                    }

                    // Refresh status batch
                    $batch->refresh();
                    $batch->total_installed = $batch->items()->where('install_status', \App\Services\DocumentSync\DTO\InstallItemResult::STATUS_SUCCESS)->count();
                    $batch->total_failed = $batch->items()->whereNotIn('install_status', [\App\Services\DocumentSync\DTO\InstallItemResult::STATUS_PENDING, \App\Services\DocumentSync\DTO\InstallItemResult::STATUS_PROCESSING, \App\Services\DocumentSync\DTO\InstallItemResult::STATUS_SUCCESS, 'SKIPPED'])->count();
                    $batch->save();

                    Notification::make()
                        ->title('Pemulihan Batch Selesai')
                        ->body("Hasil pemulihan: {$confirmed} dikonfirmasi ATTACHED, {$reset} direset ke FAILED, {$skipped} dilewati (masih aktif).")
                        ->info()
                        ->send();
                }),
        ];
    }

    public function getRelationManagers(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }
}
