<?php

namespace Tests\Unit\DocumentSync;

use App\Services\DocumentSync\DTO\ScanResult;
use App\Services\DocumentSync\FolderScannerService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FolderScannerServiceTest extends TestCase
{
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gsr_scan_test_' . uniqid();
        File::makeDirectory($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }

        parent::tearDown();
    }

    public function test_scans_valid_source_directory_and_computes_sha256(): void
    {
        $content = "Sample PDF content for scanner test.";
        $filePath = $this->tempDir . DIRECTORY_SEPARATOR . '273.101 Preparasi Rev 15.pdf';
        file_put_contents($filePath, $content);

        $scanner = new FolderScannerService();
        $result = $scanner->scanDirectory($this->tempDir);

        $this->assertFalse($result->isPartial);
        $this->assertNull($result->partialReason);
        $this->assertSame(1, $result->fileCount());
        $this->assertFalse($result->hasErrors());

        $scanned = $result->files[0];
        $this->assertSame('273.101 Preparasi Rev 15.pdf', $scanned->filename);
        $this->assertSame('273.101 Preparasi Rev 15.pdf', $scanned->relativePath);
        $this->assertSame('pdf', $scanned->extension);
        $this->assertSame(strlen($content), $scanned->sizeBytes);
        $this->assertSame(hash('sha256', $content), $scanned->hash);

        // Pastikan file sumber tidak diubah atau dihapus
        $this->assertFileExists($filePath);
        $this->assertSame($content, file_get_contents($filePath));
    }

    public function test_rejects_disallowed_extensions_and_office_temp_files(): void
    {
        // Berkas valid
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'valid.pdf', 'valid pdf');
        // Ekstensi ditolak
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'archive.zip', 'zip content');
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'script.exe', 'exe content');
        // File temporary Office
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . '~$273.101.docx', 'temp office lock');

        $scanner = new FolderScannerService();
        $result = $scanner->scanDirectory($this->tempDir);

        $this->assertSame(1, $result->fileCount());
        $this->assertSame('valid.pdf', $result->files[0]->filename);
        $this->assertArrayHasKey('archive.zip', $result->skippedFiles);
        $this->assertArrayHasKey('script.exe', $result->skippedFiles);
        $this->assertArrayHasKey('~$273.101.docx', $result->skippedFiles);
    }

    public function test_rejects_file_exceeding_max_file_size(): void
    {
        config(['document_sync.max_file_size_mb' => 1]); // Batas 1 MB

        $filePath = $this->tempDir . DIRECTORY_SEPARATOR . 'large.pdf';
        // Buat file 1.5 MB
        $handle = fopen($filePath, 'wb');
        fseek($handle, 1.5 * 1024 * 1024 - 1);
        fwrite($handle, 'x');
        fclose($handle);

        $scanner = new FolderScannerService();
        $result = $scanner->scanDirectory($this->tempDir);

        $this->assertSame(0, $result->fileCount());
        $this->assertArrayHasKey('large.pdf', $result->skippedFiles);
    }

    public function test_handles_non_existent_source_directory_gracefully(): void
    {
        $nonExistent = $this->tempDir . DIRECTORY_SEPARATOR . 'non_existent_folder';

        $scanner = new FolderScannerService();
        $result = $scanner->scanDirectory($nonExistent);

        $this->assertSame(0, $result->fileCount());
        $this->assertTrue($result->hasErrors());
        $this->assertNotNull($result->errorMessage);
        $this->assertSame(ScanResult::REASON_DIR_NOT_FOUND, $result->partialReason);
    }

    public function test_stops_and_marks_partial_when_file_limit_reached(): void
    {
        config(['document_sync.max_files_per_batch' => 2]); // Batas hanya 2 file

        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'file1.pdf', 'content 1');
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'file2.pdf', 'content 2');
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'file3.pdf', 'content 3');

        $scanner = new FolderScannerService();
        $result = $scanner->scanDirectory($this->tempDir);

        $this->assertSame(2, $result->fileCount());
        $this->assertTrue($result->isPartial, 'Hasil harus menyatakan pemindaian parsial.');
        $this->assertSame(ScanResult::REASON_LIMIT_FILES, $result->partialReason);
    }

    public function test_stops_and_marks_partial_when_total_batch_size_limit_reached(): void
    {
        config([
            'document_sync.max_files_per_batch' => 10,
            'document_sync.max_file_size_mb' => 10,
            'document_sync.max_total_batch_size_mb' => 1, // Total batch limit 1 MB
        ]);

        // Buat file 700 KB
        $file1 = $this->tempDir . DIRECTORY_SEPARATOR . 'file1.pdf';
        $h1 = fopen($file1, 'wb');
        fseek($h1, 700 * 1024 - 1);
        fwrite($h1, 'a');
        fclose($h1);

        // Buat file kedua 500 KB (total 1.2 MB > 1 MB)
        $file2 = $this->tempDir . DIRECTORY_SEPARATOR . 'file2.pdf';
        $h2 = fopen($file2, 'wb');
        fseek($h2, 500 * 1024 - 1);
        fwrite($h2, 'b');
        fclose($h2);

        $scanner = new FolderScannerService();
        $result = $scanner->scanDirectory($this->tempDir);

        $this->assertSame(1, $result->fileCount());
        $this->assertTrue($result->isPartial);
        $this->assertSame(ScanResult::REASON_LIMIT_SIZE, $result->partialReason);
    }

    public function test_scans_nested_subfolders_with_relative_paths(): void
    {
        $subDir = $this->tempDir . DIRECTORY_SEPARATOR . 'batch_a' . DIRECTORY_SEPARATOR . 'forms';
        File::makeDirectory($subDir, 0755, true);

        file_put_contents($subDir . DIRECTORY_SEPARATOR . 'form1.xlsx', 'excel content');

        $scanner = new FolderScannerService();
        $result = $scanner->scanDirectory($this->tempDir);

        $this->assertSame(1, $result->fileCount());
        $this->assertSame('form1.xlsx', $result->files[0]->filename);
        $this->assertSame('batch_a/forms/form1.xlsx', $result->files[0]->relativePath);
    }
}
