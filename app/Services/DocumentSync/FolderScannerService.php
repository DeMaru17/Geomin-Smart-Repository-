<?php

namespace App\Services\DocumentSync;

use App\Services\DocumentSync\DTO\ScannedFileInfo;
use App\Services\DocumentSync\DTO\ScanResult;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

class FolderScannerService
{
    /**
     * Memindai direktori sumber berkas impor.
     *
     * @param string|null $overridePath Path kustom untuk kebutuhan pengujian
     */
    public function scanDirectory(?string $overridePath = null): ScanResult
    {
        $configuredPath = $overridePath ?? config('document_sync.source_path', storage_path('app/private/document-import'));
        $maxFiles = (int) config('document_sync.max_files_per_batch', 500);
        $maxFileSizeMb = (int) config('document_sync.max_file_size_mb', 100);
        $maxTotalBatchSizeMb = (int) config('document_sync.max_total_batch_size_mb', 1024);
        $allowedExtensions = config('document_sync.allowed_extensions', ['pdf', 'doc', 'docx', 'xls', 'xlsx']);

        $maxFileSizeBytes = $maxFileSizeMb * 1024 * 1024;
        $maxTotalBatchSizeBytes = $maxTotalBatchSizeMb * 1024 * 1024;

        // 1. Validasi keberadaan direktori sumber
        if (! is_dir($configuredPath)) {
            return new ScanResult(
                files: [],
                isPartial: false,
                totalFilesEncountered: 0,
                totalSizeBytes: 0,
                errors: ["Direktori sumber tidak ditemukan atau bukan direktori: {$configuredPath}"],
                errorMessage: "Direktori sumber tidak valid: {$configuredPath}",
                partialReason: ScanResult::REASON_DIR_NOT_FOUND
            );
        }

        $realBaseDir = realpath($configuredPath);
        if ($realBaseDir === false) {
            return new ScanResult(
                files: [],
                isPartial: false,
                totalFilesEncountered: 0,
                totalSizeBytes: 0,
                errors: ["Gagal menyelesaikan realpath untuk direktori: {$configuredPath}"],
                errorMessage: "Direktori sumber tidak dapat diakses.",
                partialReason: ScanResult::REASON_DIR_UNREADABLE
            );
        }

        $scannedFiles = [];
        $skippedFiles = [];
        $errors = [];
        $totalFilesEncountered = 0;
        $currentTotalSizeBytes = 0;
        $isPartial = false;
        $partialReason = null;

        try {
            // Gunakan RecursiveDirectoryIterator tanpa mengikuti symlink
            $dirIterator = new RecursiveDirectoryIterator(
                $realBaseDir,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO
            );

            $iterator = new RecursiveIteratorIterator($dirIterator, RecursiveIteratorIterator::LEAVES_ONLY);

            foreach ($iterator as $filePath => $fileInfo) {
                // Abaikan jika symlink
                if ($fileInfo->isLink() || is_link($filePath)) {
                    $skippedFiles[$filePath] = 'Ditolak: Tautan simbolik (symlink) tidak diizinkan.';
                    continue;
                }

                // Abaikan jika bukan regular file
                if (! $fileInfo->isFile()) {
                    continue;
                }

                $totalFilesEncountered++;

                // Validasi aksesibilitas berkas dan containment (anti-path-traversal)
                $realFilePath = realpath($filePath);
                if ($realFilePath === false) {
                    $skippedFiles[$filePath] = 'Ditolak: File tidak dapat diakses atau hilang saat pemindaian.';
                    $errors[] = "Berkas tidak dapat diakses atau hilang: {$filePath}";
                    continue;
                }

                if (! str_starts_with($realFilePath, $realBaseDir . DIRECTORY_SEPARATOR)) {
                    $skippedFiles[$filePath] = 'Ditolak: File berada di luar direktori sumber yang diizinkan (Path Traversal).';
                    continue;
                }

                $filename = $fileInfo->getFilename();

                // 2. Tolak file temporary Office (~$...)
                if (str_starts_with($filename, '~$')) {
                    $skippedFiles[$filename] = 'Ditolak: File temporary Office/lock file diabaikan.';
                    continue;
                }

                // 3. Validasi ekstensi
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (! in_array($extension, $allowedExtensions, true)) {
                    $skippedFiles[$filename] = "Ditolak: Ekstensi [{$extension}] tidak diizinkan.";
                    continue;
                }

                // 4. Validasi ukuran file tunggal (tangani potensi file error/race condition)
                try {
                    $fileSizeBytes = $fileInfo->getSize();
                } catch (Throwable $e) {
                    $skippedFiles[$filename] = 'Gagal membaca ukuran berkas: ' . $e->getMessage();
                    $errors[] = "Gagal membaca atribut ukuran berkas [{$filename}]: " . $e->getMessage();
                    continue;
                }

                if ($fileSizeBytes > $maxFileSizeBytes) {
                    $skippedFiles[$filename] = "Ditolak: Ukuran file ({$fileSizeBytes} bytes) melebihi batas maksimal ({$maxFileSizeBytes} bytes).";
                    continue;
                }

                // Hitung path relatif terhadap direktori sumber
                $relativePath = ltrim(substr($realFilePath, strlen($realBaseDir)), DIRECTORY_SEPARATOR);
                // Normalisasi path separator ke forward slash '/'
                $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

                // 5. Cek batas kuota total batch (jumlah file & total ukuran)
                if (count($scannedFiles) >= $maxFiles) {
                    $isPartial = true;
                    $partialReason = ScanResult::REASON_LIMIT_FILES;
                    $skippedFiles[$filename] = "Dilewati: Batas maksimal file per batch ({$maxFiles}) telah tercapai.";
                    break;
                }

                if (($currentTotalSizeBytes + $fileSizeBytes) > $maxTotalBatchSizeBytes) {
                    $isPartial = true;
                    $partialReason = ScanResult::REASON_LIMIT_SIZE;
                    $skippedFiles[$filename] = "Dilewati: Batas total ukuran batch ({$maxTotalBatchSizeMb} MB) telah tercapai.";
                    break;
                }

                // 6. Streaming SHA-256 hash
                try {
                    $hash = $this->computeStreamingSha256($realFilePath);
                } catch (Throwable $e) {
                    $errors[] = "Gagal membaca/menghitung hash untuk file [{$relativePath}]: " . $e->getMessage();
                    $skippedFiles[$filename] = 'Gagal membaca isi file: ' . $e->getMessage();
                    continue;
                }

                $scannedFiles[] = new ScannedFileInfo(
                    filename: $filename,
                    relativePath: $relativePath,
                    absolutePath: $realFilePath,
                    extension: $extension,
                    sizeBytes: $fileSizeBytes,
                    hash: $hash
                );

                $currentTotalSizeBytes += $fileSizeBytes;
            }
        } catch (Throwable $e) {
            $errors[] = 'Terjadi kesalahan sistem saat pemindaian: ' . $e->getMessage();

            return new ScanResult(
                files: $scannedFiles,
                isPartial: true,
                totalFilesEncountered: $totalFilesEncountered,
                totalSizeBytes: $currentTotalSizeBytes,
                skippedFiles: $skippedFiles,
                errors: $errors,
                errorMessage: 'Pemindaian terhenti sebagian karena error: ' . $e->getMessage(),
                partialReason: ScanResult::REASON_ERROR
            );
        }

        return new ScanResult(
            files: $scannedFiles,
            isPartial: $isPartial,
            totalFilesEncountered: $totalFilesEncountered,
            totalSizeBytes: $currentTotalSizeBytes,
            skippedFiles: $skippedFiles,
            errors: $errors,
            partialReason: $partialReason
        );
    }

    /**
     * Menghitung hash SHA-256 file secara streaming tanpa memuat seluruh isi file ke memori.
     */
    public function computeStreamingSha256(string $filePath): string
    {
        $handle = @fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Tidak dapat membuka file untuk membaca: {$filePath}");
        }

        $ctx = hash_init('sha256');

        while (! feof($handle)) {
            $buffer = fread($handle, 1048576); // Chunk 1 MB
            if ($buffer === false) {
                fclose($handle);
                throw new RuntimeException("Gagal membaca chunk data dari file: {$filePath}");
            }
            hash_update($ctx, $buffer);
        }

        fclose($handle);

        return hash_final($ctx);
    }
}
