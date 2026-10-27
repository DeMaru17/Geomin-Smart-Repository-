<?php

namespace App\Services\DocumentSync;

use App\Models\DocumentRevision;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PlaceholderDetectorService
{
    public const STATUS_EMPTY = 'PATH_EMPTY';

    public const STATUS_PHYSICAL_EXISTS = 'PHYSICAL_FILE_EXISTS';

    public const STATUS_PLACEHOLDER_INTERNAL = 'SEEDER_PLACEHOLDER_INTERNAL';

    public const STATUS_PLACEHOLDER_EXTERNAL = 'SEEDER_PLACEHOLDER_EXTERNAL';

    public const STATUS_BROKEN_REFERENCE = 'BROKEN_REFERENCE';

    public const STATUS_STORAGE_UNREACHABLE = 'STORAGE_UNREACHABLE';

    /**
     * Pola placeholder dari DocumentSeeder.php:
     * 'documents/' . Str::slug($documentNumber) . '_rev' . $revisionNumber . '.pdf'
     * Contoh: documents/273101_rev15.pdf, documents/273101101_rev09.pdf
     */
    public const PATTERN_INTERNAL_SEEDER = '/^documents\/273\d{3,6}_rev\d{2}\.pdf$/i';

    /**
     * Pola placeholder dari ExternalDocumentSeeder.php:
     * 'documents/external/' . Str::slug($docNumber) . '.pdf'
     * Contoh: documents/external/e001.pdf
     */
    public const PATTERN_EXTERNAL_SEEDER = '/^documents\/external\/[a-z0-9\-]+\.pdf$/i';

    /**
     * Mendeteksi status path berkas pada DocumentRevision.
     */
    public function detectForRevision(DocumentRevision $revision, string $slot = 'file_path', ?string $disk = null): string
    {
        $path = $slot === 'word_file_path' ? $revision->word_file_path : $revision->file_path;

        return $this->detectPath($path, $disk);
    }

    /**
     * Mendeteksi status string path dan keberadaan fisik berkas pada disk storage.
     */
    public function detectPath(?string $path, ?string $disk = null): string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return self::STATUS_EMPTY;
        }

        $storageDisk = $disk ?? config('document_sync.disk', 'local');

        // Cek akses storage dan keberadaan file fisik
        try {
            $root = Storage::disk($storageDisk)->path('');
            if (! is_dir($root) || ! is_readable($root)) {
                return self::STATUS_STORAGE_UNREACHABLE;
            }

            if (Storage::disk($storageDisk)->exists($path)) {
                return self::STATUS_PHYSICAL_EXISTS;
            }
        } catch (Throwable $e) {
            // Jika storage tidak dapat diakses (I/O error, network error, driver exception, dll.)
            return self::STATUS_STORAGE_UNREACHABLE;
        }

        // File fisik tidak ada di storage, periksa apakah path sesuai pola seeder resmi
        if (preg_match(self::PATTERN_INTERNAL_SEEDER, $path)) {
            return self::STATUS_PLACEHOLDER_INTERNAL;
        }

        if (preg_match(self::PATTERN_EXTERNAL_SEEDER, $path)) {
            return self::STATUS_PLACEHOLDER_EXTERNAL;
        }

        // Jika tidak ada di fisik dan bukan pola seeder yang dikenal
        return self::STATUS_BROKEN_REFERENCE;
    }

    /**
     * Memeriksa apakah status merupakan placeholder seeder yang sah.
     */
    public function isRecognizedPlaceholder(string $status): bool
    {
        return in_array($status, [
            self::STATUS_PLACEHOLDER_INTERNAL,
            self::STATUS_PLACEHOLDER_EXTERNAL,
        ], true);
    }
}
