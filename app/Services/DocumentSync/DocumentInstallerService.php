<?php

namespace App\Services\DocumentSync;

use App\Models\Document;
use App\Models\DocumentImportBatch;
use App\Models\DocumentImportItem;
use App\Models\DocumentRevision;
use App\Services\DocumentSync\DTO\InstallBatchResult;
use App\Services\DocumentSync\DTO\InstallItemResult;
use App\Services\DocumentSync\DTO\RecoverItemResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DocumentInstallerService
{
    public function __construct(
        protected PlaceholderDetectorService $placeholderDetector
    ) {}

    /**
     * Memasang satu item berkas hasil pemindaian ke revisi dokumen target.
     */
    public function installItem(DocumentImportItem $item): InstallItemResult
    {
        // 1. Pencegahan pemasangan ganda
        if ($item->install_status === InstallItemResult::STATUS_SUCCESS) {
            return new InstallItemResult(
                item: $item,
                status: InstallItemResult::STATUS_ALREADY_ATTACHED,
                isSuccess: false,
                installedPath: $item->installed_path,
                errorMessage: 'Item ini sudah pernah berhasil dipasang sebelumnya (dilarang memasang ganda).'
            );
        }

        // Transisi status atomik untuk mencegah race condition / double-click bersamaan
        $affected = DocumentImportItem::where('id', $item->id)
            ->whereIn('install_status', ['PENDING', 'FAILED', 'SOURCE_CHANGED', 'SOURCE_MISSING', 'SKIPPED'])
            ->update(['install_status' => 'PROCESSING']);

        if ($affected === 0) {
            return new InstallItemResult(
                item: $item->fresh(),
                status: InstallItemResult::STATUS_FAILED,
                isSuccess: false,
                errorMessage: 'Item sedang diproses oleh permintaan lain atau status tidak mengizinkan pemasangan.'
            );
        }

        $item->refresh();

        // 2. Validasi kelayakan item impor
        $eligibleDecisions = ['READY_TO_ATTACH', 'PLACEHOLDER_REPLACEABLE', 'MANUAL_RESOLVED'];
        if (! in_array($item->decision, $eligibleDecisions, true)) {
            $this->failItem($item, InstallItemResult::STATUS_SKIPPED, "Keputusan item [{$item->decision}] tidak memenuhi syarat untuk dipasang.");

            return new InstallItemResult($item, InstallItemResult::STATUS_SKIPPED, false, null, $item->error_message);
        }

        if (! $item->matched_document_id || ! $item->matched_revision_id || ! $item->target_slot) {
            $this->failItem($item, InstallItemResult::STATUS_TARGET_INVALID, 'Data target dokumen, revisi, atau slot belum lengkap.');

            return new InstallItemResult($item, InstallItemResult::STATUS_TARGET_INVALID, false, null, $item->error_message);
        }

        $diskName = config('document_sync.disk', 'local');
        $sourceBaseDir = config('document_sync.source_path', storage_path('app/private/document-import'));
        $realSourceBaseDir = realpath($sourceBaseDir);

        if ($realSourceBaseDir === false || ! is_dir($realSourceBaseDir)) {
            $this->failItem($item, InstallItemResult::STATUS_FAILED, "Direktori sumber berkas tidak ditemukan atau tidak dapat diakses: {$sourceBaseDir}");

            return new InstallItemResult($item, InstallItemResult::STATUS_FAILED, false, null, $item->error_message);
        }

        // 3. Validasi berkas sumber (keberadaan, containment, tipe, hash, ukuran)
        $sourceFullPath = $realSourceBaseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $item->source_relative_path);

        if (! file_exists($sourceFullPath)) {
            $this->failItem($item, InstallItemResult::STATUS_SOURCE_MISSING, "Berkas sumber tidak ditemukan di direktori impor: {$item->source_relative_path}");

            return new InstallItemResult($item, InstallItemResult::STATUS_SOURCE_MISSING, false, null, $item->error_message);
        }

        $realSourceFile = realpath($sourceFullPath);
        if ($realSourceFile === false || ! str_starts_with($realSourceFile, $realSourceBaseDir . DIRECTORY_SEPARATOR)) {
            $this->failItem($item, InstallItemResult::STATUS_FAILED, 'Akses berkas ditolak: Path berkas sumber berada di luar direktori yang diizinkan.');

            return new InstallItemResult($item, InstallItemResult::STATUS_FAILED, false, null, $item->error_message);
        }

        if (is_link($sourceFullPath) || is_link($realSourceFile)) {
            $this->failItem($item, InstallItemResult::STATUS_FAILED, 'Tautan simbolik (symlink) ditolak demi keamanan.');

            return new InstallItemResult($item, InstallItemResult::STATUS_FAILED, false, null, $item->error_message);
        }

        if (str_starts_with(basename($realSourceFile), '~$')) {
            $this->failItem($item, InstallItemResult::STATUS_FAILED, 'Berkas temporer Microsoft Office ditolak.');

            return new InstallItemResult($item, InstallItemResult::STATUS_FAILED, false, null, $item->error_message);
        }

        $currentSize = filesize($realSourceFile);
        if ($currentSize !== (int) $item->file_size_bytes) {
            $this->failItem($item, InstallItemResult::STATUS_SOURCE_CHANGED, "Ukuran berkas sumber berubah sejak pemindaian (sebelumnya {$item->file_size_bytes} bytes, sekarang {$currentSize} bytes).");

            return new InstallItemResult($item, InstallItemResult::STATUS_SOURCE_CHANGED, false, null, $item->error_message);
        }

        $currentHash = hash_file('sha256', $realSourceFile);
        if ($currentHash !== $item->file_hash) {
            $this->failItem($item, InstallItemResult::STATUS_SOURCE_CHANGED, 'Checksum SHA-256 berkas sumber berubah sejak pemindaian (berkas telah dimodifikasi).');

            return new InstallItemResult($item, InstallItemResult::STATUS_SOURCE_CHANGED, false, null, $item->error_message);
        }

        // 4. Verifikasi ulang Dokumen dan Revisi target secara live dari database
        $document = Document::with(['revisions', 'category'])->find($item->matched_document_id);
        if (! $document) {
            $this->failItem($item, InstallItemResult::STATUS_TARGET_INVALID, "Dokumen target [ID: {$item->matched_document_id}] tidak lagi ditemukan di database.");

            return new InstallItemResult($item, InstallItemResult::STATUS_TARGET_INVALID, false, null, $item->error_message);
        }

        $revision = $document->revisions->firstWhere('id', $item->matched_revision_id);
        if (! $revision) {
            $this->failItem($item, InstallItemResult::STATUS_TARGET_INVALID, "Revisi target [ID: {$item->matched_revision_id}] tidak lagi ditemukan pada dokumen [{$document->document_number}].");

            return new InstallItemResult($item, InstallItemResult::STATUS_TARGET_INVALID, false, null, $item->error_message);
        }

        // Verifikasi aturan kategori dan ekstensi
        $categoryCode = $document->category?->code ?? ($document->is_external ? 'EXTERNAL' : null);
        $ext = strtolower($item->file_extension);

        if ($categoryCode === 'PM') {
            $this->failItem($item, InstallItemResult::STATUS_TARGET_INVALID, 'Kategori Panduan Mutu (PM) belum memiliki aturan pemasangan terbukti dan dilarang dipasang otomatis.');

            return new InstallItemResult($item, InstallItemResult::STATUS_TARGET_INVALID, false, null, $item->error_message);
        }

        if (in_array($categoryCode, ['PK', 'IK'], true)) {
            if ($ext === 'pdf' && $item->target_slot !== 'file_path') {
                $this->failItem($item, InstallItemResult::STATUS_TARGET_INVALID, "Berkas PDF kategori {$categoryCode} hanya boleh dipasang ke slot file_path.");

                return new InstallItemResult($item, InstallItemResult::STATUS_TARGET_INVALID, false, null, $item->error_message);
            }
            if (in_array($ext, ['doc', 'docx'], true) && $item->target_slot !== 'word_file_path') {
                $this->failItem($item, InstallItemResult::STATUS_TARGET_INVALID, "Berkas Word kategori {$categoryCode} hanya boleh dipasang ke slot word_file_path.");

                return new InstallItemResult($item, InstallItemResult::STATUS_TARGET_INVALID, false, null, $item->error_message);
            }
            if (! in_array($ext, ['pdf', 'doc', 'docx'], true)) {
                $this->failItem($item, InstallItemResult::STATUS_TARGET_INVALID, "Ekstensi [{$ext}] tidak diizinkan untuk kategori {$categoryCode}.");

                return new InstallItemResult($item, InstallItemResult::STATUS_TARGET_INVALID, false, null, $item->error_message);
            }
        } elseif ($categoryCode === 'F') {
            if ($ext === 'pdf') {
                $this->failItem($item, InstallItemResult::STATUS_TARGET_INVALID, 'Berkas PDF tidak diizinkan untuk kategori Formulir.');

                return new InstallItemResult($item, InstallItemResult::STATUS_TARGET_INVALID, false, null, $item->error_message);
            }
            if (in_array($ext, ['xls', 'xlsx'], true)) {
                if ($item->target_slot !== 'file_path' && ! ($item->role_hint === 'master' && $item->target_slot === 'word_file_path')) {
                    $this->failItem($item, InstallItemResult::STATUS_TARGET_INVALID, 'Berkas Excel untuk dokumen Formulir harus masuk ke slot file_path.');

                    return new InstallItemResult($item, InstallItemResult::STATUS_TARGET_INVALID, false, null, $item->error_message);
                }
            } elseif (in_array($ext, ['doc', 'docx'], true)) {
                if ($item->target_slot !== 'word_file_path') {
                    $this->failItem($item, InstallItemResult::STATUS_TARGET_INVALID, 'Master Word Formulir harus dipasang ke slot word_file_path.');

                    return new InstallItemResult($item, InstallItemResult::STATUS_TARGET_INVALID, false, null, $item->error_message);
                }
            } else {
                $this->failItem($item, InstallItemResult::STATUS_TARGET_INVALID, "Ekstensi [{$ext}] tidak diizinkan untuk kategori Formulir.");

                return new InstallItemResult($item, InstallItemResult::STATUS_TARGET_INVALID, false, null, $item->error_message);
            }
        } elseif ($categoryCode === 'EXTERNAL') {
            if ($ext !== 'pdf' || $item->target_slot !== 'file_path') {
                $this->failItem($item, InstallItemResult::STATUS_TARGET_INVALID, 'Dokumen Eksternal hanya menerima berkas PDF pada slot file_path.');

                return new InstallItemResult($item, InstallItemResult::STATUS_TARGET_INVALID, false, null, $item->error_message);
            }
        } else {
            $this->failItem($item, InstallItemResult::STATUS_TARGET_INVALID, "Kategori [{$categoryCode}] belum didukung untuk pemasangan otomatis.");

            return new InstallItemResult($item, InstallItemResult::STATUS_TARGET_INVALID, false, null, $item->error_message);
        }

        // 5. Validasi kondisi slot target saat ini
        $currentSlotValue = $item->target_slot === 'word_file_path' ? $revision->word_file_path : $revision->file_path;
        $slotStatus = $this->placeholderDetector->detectPath($currentSlotValue, $diskName);

        if ($slotStatus === PlaceholderDetectorService::STATUS_PHYSICAL_EXISTS) {
            $this->failItem($item, InstallItemResult::STATUS_SLOT_OCCUPIED, "Slot target [{$item->target_slot}] saat ini sudah terisi berkas fisik valid pada storage (dilarang menimpa).");

            return new InstallItemResult($item, InstallItemResult::STATUS_SLOT_OCCUPIED, false, null, $item->error_message);
        }

        if ($slotStatus === PlaceholderDetectorService::STATUS_STORAGE_UNREACHABLE) {
            $this->failItem($item, InstallItemResult::STATUS_FAILED, 'Media penyimpanan storage tidak dapat diakses saat memverifikasi slot target.');

            return new InstallItemResult($item, InstallItemResult::STATUS_FAILED, false, null, $item->error_message);
        }

        if ($slotStatus === PlaceholderDetectorService::STATUS_BROKEN_REFERENCE) {
            $this->failItem($item, InstallItemResult::STATUS_TARGET_INVALID, 'Slot target memiliki path tercatat namun berkas fisik tidak ada dan bukan placeholder seeder resmi.');

            return new InstallItemResult($item, InstallItemResult::STATUS_TARGET_INVALID, false, null, $item->error_message);
        }

        if (! in_array($slotStatus, [
            PlaceholderDetectorService::STATUS_EMPTY,
            PlaceholderDetectorService::STATUS_PLACEHOLDER_INTERNAL,
            PlaceholderDetectorService::STATUS_PLACEHOLDER_EXTERNAL,
        ], true)) {
            $this->failItem($item, InstallItemResult::STATUS_TARGET_INVALID, "Status slot target [{$slotStatus}] tidak mengizinkan pemasangan berkas.");

            return new InstallItemResult($item, InstallItemResult::STATUS_TARGET_INVALID, false, null, $item->error_message);
        }

        // 6. Penentuan nama berkas dan lokasi tujuan
        $destDir = $item->target_slot === 'word_file_path'
            ? config('document_sync.word_directory', 'geomin-documents/word')
            : config('document_sync.pdf_directory', 'geomin-documents');

        $safeDocNumber = Str::slug($document->document_number);
        $safeRevNumber = $revision->revision_number;
        $hashPrefix = substr($item->file_hash, 0, 8);
        $destFilename = "{$safeDocNumber}_rev{$safeRevNumber}_{$hashPrefix}.{$ext}";
        $destRelativePath = "{$destDir}/{$destFilename}";

        // Pencegahan penimpaan: jika berkas tujuan sudah ada, buat nama unik baru
        if (Storage::disk($diskName)->exists($destRelativePath)) {
            $uniqueSuffix = bin2hex(random_bytes(4));
            $destFilename = "{$safeDocNumber}_rev{$safeRevNumber}_{$hashPrefix}_{$uniqueSuffix}.{$ext}";
            $destRelativePath = "{$destDir}/{$destFilename}";

            if (Storage::disk($diskName)->exists($destRelativePath)) {
                $this->failItem($item, InstallItemResult::STATUS_FAILED, 'Gagal menghasilkan nama berkas tujuan yang unik pada storage.');

                return new InstallItemResult($item, InstallItemResult::STATUS_FAILED, false, null, $item->error_message);
            }
        }

        // 7. Salin berkas secara fisik (Streaming)
        $sourceStream = @fopen($realSourceFile, 'rb');
        if ($sourceStream === false) {
            $this->failItem($item, InstallItemResult::STATUS_FAILED, 'Gagal membuka stream baca berkas sumber.');

            return new InstallItemResult($item, InstallItemResult::STATUS_FAILED, false, null, $item->error_message);
        }

        try {
            $writeSuccess = Storage::disk($diskName)->put($destRelativePath, $sourceStream);
        } finally {
            if (is_resource($sourceStream)) {
                fclose($sourceStream);
            }
        }

        if (! $writeSuccess || ! Storage::disk($diskName)->exists($destRelativePath)) {
            $this->failItem($item, InstallItemResult::STATUS_FAILED, "Gagal menulis berkas ke media penyimpanan target [{$destRelativePath}].");

            return new InstallItemResult($item, InstallItemResult::STATUS_FAILED, false, null, $item->error_message);
        }

        // 8. Verifikasi integritas berkas hasil salinan
        $targetSize = Storage::disk($diskName)->size($destRelativePath);
        $targetStream = Storage::disk($diskName)->readStream($destRelativePath);
        $ctx = hash_init('sha256');
        while (! feof($targetStream)) {
            hash_update($ctx, fread($targetStream, 1048576));
        }
        fclose($targetStream);
        $targetHash = hash_final($ctx);

        if ($targetSize !== $currentSize || $targetHash !== $currentHash) {
            // Kompensasi penghapusan berkas rusak
            Storage::disk($diskName)->delete($destRelativePath);
            $this->failItem($item, InstallItemResult::STATUS_FAILED, 'Verifikasi integritas berkas hasil salinan gagal (ukuran atau checksum tidak identik).');

            return new InstallItemResult($item, InstallItemResult::STATUS_FAILED, false, null, $item->error_message);
        }

        // 9. Transaksi Database dengan Penguncian (Locking) & Kompensasi Rollback
        $committed = false;
        try {
            DB::connection('mysql')->transaction(function () use ($item, $revision, $destRelativePath, $diskName, &$committed) {
                // Kunci baris revisi target untuk mencegah modifikasi paralel
                $lockedRev = DocumentRevision::where('id', $revision->id)->lockForUpdate()->first();
                if (! $lockedRev) {
                    throw new RuntimeException("Revisi target [ID: {$revision->id}] hilang saat transaksi penguncian.");
                }

                $slotField = $item->target_slot;
                $lockedSlotValue = $lockedRev->{$slotField};

                // Periksa apakah slot telah diisi secara konkuren oleh transaksi lain
                if (! empty($lockedSlotValue) && Storage::disk($diskName)->exists($lockedSlotValue)) {
                    throw new RuntimeException("Konflik konkuren: Slot target [{$slotField}] telah diisi oleh transaksi lain.");
                }

                // Perbarui path pada DocumentRevision (event updated akan men-dispatch ExtractPdfTextJob yang menunggu commit)
                $lockedRev->update([$slotField => $destRelativePath]);

                // Perbarui status staging item
                $item->update([
                    'install_status' => InstallItemResult::STATUS_SUCCESS,
                    'installed_path' => $destRelativePath,
                    'error_message' => null,
                ]);

                DB::connection('mysql')->afterCommit(function () use (&$committed) {
                    $committed = true;
                });
            });
        } catch (Throwable $dbEx) {
            // Periksa apakah database sebenarnya telah berhasil meng-commit path ini
            $isReferencedInDb = false;
            try {
                $freshRev = DocumentRevision::find($revision->id);
                if ($freshRev && $freshRev->{$item->target_slot} === $destRelativePath) {
                    $isReferencedInDb = true;
                }
            } catch (Throwable) {
                // Abaikan error saat pengecekan fallback
            }

            if (! $isReferencedInDb && ! $committed) {
                // Transaksi BENAR-BENAR belum commit: Aman untuk menghapus berkas baru yang dibuat oleh proses ini
                try {
                    if (Storage::disk($diskName)->exists($destRelativePath)) {
                        Storage::disk($diskName)->delete($destRelativePath);
                    }
                } catch (Throwable $compEx) {
                    Log::critical("Kompensasi rollback gagal: Berkas [{$destRelativePath}] gagal dihapus: " . $compEx->getMessage(), [
                        'exception' => $compEx,
                        'item_id' => $item->id,
                        'target_slot' => $item->target_slot,
                    ]);
                }

                $this->failItem($item, InstallItemResult::STATUS_FAILED, 'Gagal memperbarui database saat transaksi pemasangan: ' . $dbEx->getMessage());

                return new InstallItemResult($item, InstallItemResult::STATUS_FAILED, false, null, $item->error_message);
            }

            // Transaksi SUDAH berhasil commit di database. DILARANG MENGHAPUS berkas yang sudah direferensikan DB!
            Log::warning("Peringatan: Terjadi exception pasca-commit pada pemasangan berkas [{$destRelativePath}]: " . $dbEx->getMessage());

            if ($item->install_status !== InstallItemResult::STATUS_SUCCESS) {
                $item->update([
                    'install_status' => InstallItemResult::STATUS_SUCCESS,
                    'installed_path' => $destRelativePath,
                    'error_message' => 'Pemasangan berhasil di database, namun terjadi peringatan pasca-commit: ' . $dbEx->getMessage(),
                ]);
            }

            return new InstallItemResult(
                item: $item->fresh(),
                status: InstallItemResult::STATUS_SUCCESS,
                isSuccess: true,
                installedPath: $destRelativePath,
                errorMessage: 'Pemasangan berhasil dengan peringatan pasca-commit: ' . $dbEx->getMessage()
            );
        }

        return new InstallItemResult(
            item: $item->fresh(),
            status: InstallItemResult::STATUS_SUCCESS,
            isSuccess: true,
            installedPath: $destRelativePath,
            errorMessage: null
        );
    }

    /**
     * Memasang kumpulan berkas dalam batch pemindaian.
     *
     * @param array<int>|null $itemIds Jika diberikan, hanya pasang item dengan ID tersebut
     */
    public function installBatch(DocumentImportBatch $batch, ?array $itemIds = null): InstallBatchResult
    {
        if (! in_array($batch->status, ['Preview_Ready', 'Processing'], true)) {
            return new InstallBatchResult(
                batch: $batch,
                totalProcessed: 0,
                totalInstalled: 0,
                totalSkipped: 0,
                totalFailed: 0,
                errorMessage: "Status batch [{$batch->status}] tidak mengizinkan eksekusi pemasangan berkas."
            );
        }

        $batch->update(['status' => 'Processing']);

        $query = $batch->items();
        if ($itemIds !== null) {
            $query->whereIn('id', $itemIds);
        } else {
            $query->where('is_selected', true);
        }

        $items = $query->get();

        $totalInstalled = 0;
        $totalSkipped = 0;
        $totalFailed = 0;
        $itemResults = [];

        foreach ($items as $item) {
            $res = $this->installItem($item);
            $itemResults[] = $res;

            if ($res->isSuccess) {
                $totalInstalled++;
            } elseif ($res->status === InstallItemResult::STATUS_SKIPPED || $res->status === InstallItemResult::STATUS_ALREADY_ATTACHED) {
                $totalSkipped++;
            } else {
                $totalFailed++;
            }
        }

        // Perbarui ringkasan statistik batch
        $batch->update([
            'total_installed' => $batch->total_installed + $totalInstalled,
            'total_skipped' => $batch->total_skipped + $totalSkipped,
            'total_failed' => $batch->total_failed + $totalFailed,
            'status' => 'Completed',
            'completed_at' => now(),
        ]);

        return new InstallBatchResult(
            batch: $batch->fresh(),
            totalProcessed: count($items),
            totalInstalled: $totalInstalled,
            totalSkipped: $totalSkipped,
            totalFailed: $totalFailed,
            itemResults: $itemResults
        );
    }

    /**
     * Memulihkan item yang tertinggal dalam status PROCESSING secara aman.
     *
     * @param int $staleSeconds Durasi minimum (dalam detik) sejak updated_at agar tidak mengganggu proses yang masih berjalan aktif
     * @param bool $force Jika true, abaikan batas waktu (misal admin yakin worker telah mati)
     */
    public function recoverStuckItem(DocumentImportItem $item, int $staleSeconds = 300, bool $force = false): RecoverItemResult
    {
        if ($item->install_status !== 'PROCESSING') {
            return new RecoverItemResult(
                item: $item,
                action: RecoverItemResult::ACTION_NOT_STUCK,
                wasRecovered: false,
                message: "Item tidak dalam status PROCESSING (status saat ini: {$item->install_status})."
            );
        }

        // 1. Cek apakah proses kemungkinan masih berjalan aktif
        if (! $force && $item->updated_at && $item->updated_at->diffInSeconds(now()) < $staleSeconds) {
            $remaining = $staleSeconds - $item->updated_at->diffInSeconds(now());

            return new RecoverItemResult(
                item: $item,
                action: RecoverItemResult::ACTION_SKIPPED_ACTIVE,
                wasRecovered: false,
                message: "Item baru diperbarui {$item->updated_at->diffInSeconds(now())} detik lalu. Diabaikan untuk mencegah intervensi proses aktif (tunggu {$remaining} detik lagi atau gunakan force)."
            );
        }

        $diskName = config('document_sync.disk', 'local');

        // 2. Periksa apakah berkas sebenarnya SUDAH terpasang di database sebelum proses crash/terhenti
        if ($item->matched_revision_id && $item->target_slot) {
            $revision = DocumentRevision::find($item->matched_revision_id);
            if ($revision) {
                $currentSlotValue = $revision->{$item->target_slot};

                // Jika slot di database terisi dan berkas fisik ada di storage
                if (! empty($currentSlotValue) && Storage::disk($diskName)->exists($currentSlotValue)) {
                    // Verifikasi apakah ukuran dan hash-nya sesuai dengan item ini
                    $stream = Storage::disk($diskName)->readStream($currentSlotValue);
                    $ctx = hash_init('sha256');
                    while (! feof($stream)) {
                        hash_update($ctx, fread($stream, 1048576));
                    }
                    fclose($stream);
                    $actualHash = hash_final($ctx);

                    if ($actualHash === $item->file_hash) {
                        // KASUS A: Berkas terbukti SUDAH terpasang dengan benar di DB & storage!
                        // DILARANG mereset ke PENDING. Tandai sebagai ATTACHED!
                        $item->update([
                            'install_status' => InstallItemResult::STATUS_SUCCESS,
                            'installed_path' => $currentSlotValue,
                            'error_message' => 'Dipulihkan: Berkas terbukti telah berhasil terpasang di database dan storage sebelum proses terhenti.',
                        ]);

                        return new RecoverItemResult(
                            item: $item->fresh(),
                            action: RecoverItemResult::ACTION_CONFIRMED_ATTACHED,
                            wasRecovered: true,
                            message: 'Item berhasil dipulihkan dan dikonfirmasi telah terpasang di database.'
                        );
                    }
                }
            }
        }

        // KASUS B: Berkas belum terpasang di database.
        // Cek apakah ada berkas target yatim yang belum direferensikan oleh revisi mana pun
        if (! empty($item->installed_path) && Storage::disk($diskName)->exists($item->installed_path)) {
            $isReferenced = DocumentRevision::where('file_path', $item->installed_path)
                ->orWhere('word_file_path', $item->installed_path)
                ->exists();

            if (! $isReferenced) {
                try {
                    Storage::disk($diskName)->delete($item->installed_path);
                } catch (Throwable $e) {
                    Log::critical("Pembersihan berkas yatim saat pemulihan gagal: {$item->installed_path}: " . $e->getMessage());
                }
            }
        }

        // Reset status item ke FAILED (bukan reset buta ke PENDING) dengan alasan eksplisit
        $item->update([
            'install_status' => InstallItemResult::STATUS_FAILED,
            'error_message' => 'Dipulihkan: Proses sebelumnya terhenti saat status PROCESSING. Item direset ke status FAILED agar dapat diperiksa/dicoba ulang.',
        ]);

        return new RecoverItemResult(
            item: $item->fresh(),
            action: RecoverItemResult::ACTION_RESET_FAILED,
            wasRecovered: true,
            message: 'Item berhasil direset ke status FAILED untuk pemeriksaan ulang.'
        );
    }

    /**
     * Memulihkan seluruh item yang tertinggal dalam status PROCESSING pada sebuah batch.
     *
     * @return array<RecoverItemResult>
     */
    public function recoverStuckItemsInBatch(DocumentImportBatch $batch, int $staleSeconds = 300, bool $force = false): array
    {
        $stuckItems = $batch->items()->where('install_status', 'PROCESSING')->get();
        $results = [];

        foreach ($stuckItems as $item) {
            $results[] = $this->recoverStuckItem($item, $staleSeconds, $force);
        }

        return $results;
    }

    /**
     * Helper untuk memperbarui status item yang gagal atau di-skip.
     */
    protected function failItem(DocumentImportItem $item, string $status, string $message): void
    {
        $item->update([
            'install_status' => $status,
            'error_message' => $message,
        ]);
    }
}
