<?php

namespace Tests\Feature\DocumentSync;

use App\Jobs\ExtractPdfTextJob;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentImportBatch;
use App\Models\DocumentImportItem;
use App\Models\DocumentRevision;
use App\Services\DocumentSync\DTO\InstallItemResult;
use App\Services\DocumentSync\DocumentInstallerService;
use App\Services\DocumentSync\PlaceholderDetectorService;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentInstallerServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected DocumentInstallerService $installer;
    protected PlaceholderDetectorService $detector;
    protected string $tempSourceDir;
    protected DocumentCategory $pkCategory;
    protected DocumentCategory $ikCategory;
    protected DocumentCategory $fCategory;
    protected DocumentCategory $pmCategory;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', ['--database' => 'mysql']);

        Storage::fake('local');
        config(['document_sync.disk' => 'local']);

        $this->tempSourceDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gsr_source_' . uniqid();
        File::makeDirectory($this->tempSourceDir, 0755, true);
        config(['document_sync.source_path' => $this->tempSourceDir]);

        $this->detector = new PlaceholderDetectorService();
        $this->installer = new DocumentInstallerService($this->detector);

        $this->pkCategory = DocumentCategory::firstOrCreate(['code' => 'PK'], ['name' => 'Prosedur Kerja']);
        $this->ikCategory = DocumentCategory::firstOrCreate(['code' => 'IK'], ['name' => 'Instruksi Kerja']);
        $this->fCategory = DocumentCategory::firstOrCreate(['code' => 'F'], ['name' => 'Formulir']);
        $this->pmCategory = DocumentCategory::firstOrCreate(['code' => 'PM'], ['name' => 'Panduan Mutu']);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->tempSourceDir)) {
            File::deleteDirectory($this->tempSourceDir);
        }

        parent::tearDown();
    }

    protected function createBatch(): DocumentImportBatch
    {
        return DocumentImportBatch::create([
            'batch_number' => 'BATCH-TEST-' . uniqid(),
            'scanned_by' => 1,
            'source_directory' => 'document-import',
            'status' => 'Preview_Ready',
        ]);
    }

    protected function createSourceFile(string $filename, string $content = 'Dummy PDF content'): string
    {
        $filePath = $this->tempSourceDir . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($filePath, $content);

        return $filePath;
    }

    public function test_installs_pdf_to_file_path_for_pk_document(): void
    {
        $doc = Document::create([
            'document_number' => '273.101',
            'title' => 'Preparasi Sampel',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '15',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273101_rev15.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $content = 'Sample PK PDF valid content';
        $filename = '273.101 Preparasi Rev 15.pdf';
        $this->createSourceFile($filename, $content);

        $batch = $this->createBatch();
        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => $filename,
            'source_relative_path' => $filename,
            'file_extension' => 'pdf',
            'file_size_bytes' => strlen($content),
            'file_hash' => hash('sha256', $content),
            'parsed_document_number' => '273.101',
            'parsed_revision_number' => '15',
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'PLACEHOLDER_REPLACEABLE',
            'is_selected' => true,
        ]);

        $result = $this->installer->installItem($item);

        $this->assertTrue($result->isSuccess);
        $this->assertSame(InstallItemResult::STATUS_SUCCESS, $result->status);
        $this->assertNotNull($result->installedPath);
        $this->assertStringStartsWith('geomin-documents/', $result->installedPath);
        $this->assertStringEndsWith('.pdf', $result->installedPath);

        // Verifikasi fisik di storage
        Storage::disk('local')->assertExists($result->installedPath);
        $this->assertSame($content, Storage::disk('local')->get($result->installedPath));

        // Verifikasi database DocumentRevision
        $rev->refresh();
        $this->assertSame($result->installedPath, $rev->file_path);

        // Verifikasi berkas sumber tetap utuh
        $this->assertFileExists($this->tempSourceDir . DIRECTORY_SEPARATOR . $filename);
        $this->assertSame($content, file_get_contents($this->tempSourceDir . DIRECTORY_SEPARATOR . $filename));
    }

    public function test_installs_docx_to_word_file_path_for_pk_document(): void
    {
        $doc = Document::create([
            'document_number' => '273.102',
            'title' => 'Pengujian Fisik',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273102_rev01.pdf',
            'word_file_path' => null,
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $content = 'DOCX Master PK Content';
        $filename = '273.102 Pengujian Fisik Rev 01.docx';
        $this->createSourceFile($filename, $content);

        $batch = $this->createBatch();
        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => $filename,
            'source_relative_path' => $filename,
            'file_extension' => 'docx',
            'file_size_bytes' => strlen($content),
            'file_hash' => hash('sha256', $content),
            'parsed_document_number' => '273.102',
            'parsed_revision_number' => '01',
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'word_file_path',
            'decision' => 'READY_TO_ATTACH',
            'is_selected' => true,
        ]);

        $result = $this->installer->installItem($item);

        $this->assertTrue($result->isSuccess);
        $this->assertStringStartsWith('geomin-documents/word/', $result->installedPath);
        $this->assertStringEndsWith('.docx', $result->installedPath);

        $rev->refresh();
        $this->assertSame($result->installedPath, $rev->word_file_path);
        $this->assertSame('documents/273102_rev01.pdf', $rev->file_path, 'file_path tidak boleh terganggu.');
    }

    public function test_installs_excel_blanko_to_file_path_for_form_f(): void
    {
        $doc = Document::create([
            'document_number' => '273.101.201',
            'title' => 'Formulir Uji',
            'category_id' => $this->fCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273101201_rev01.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $content = 'Excel Blanko Content';
        $filename = '273.101.201 Form Uji Blanko Rev 01.xlsx';
        $this->createSourceFile($filename, $content);

        $batch = $this->createBatch();
        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => $filename,
            'source_relative_path' => $filename,
            'file_extension' => 'xlsx',
            'file_size_bytes' => strlen($content),
            'file_hash' => hash('sha256', $content),
            'parsed_document_number' => '273.101.201',
            'parsed_revision_number' => '01',
            'role_hint' => 'blanko',
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'PLACEHOLDER_REPLACEABLE',
            'is_selected' => true,
        ]);

        $result = $this->installer->installItem($item);

        $this->assertTrue($result->isSuccess);
        $this->assertStringStartsWith('geomin-documents/', $result->installedPath);
        $this->assertStringEndsWith('.xlsx', $result->installedPath);

        $rev->refresh();
        $this->assertSame($result->installedPath, $rev->file_path);
    }

    public function test_installs_excel_master_to_word_file_path_for_form_f(): void
    {
        $doc = Document::create([
            'document_number' => '273.101.202',
            'title' => 'Formulir Analisis',
            'category_id' => $this->fCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '02',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273101202_rev02.pdf',
            'word_file_path' => null,
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $content = 'Excel Master Content';
        $filename = '273.101.202 Form Master Rev 02.xlsx';
        $this->createSourceFile($filename, $content);

        $batch = $this->createBatch();
        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => $filename,
            'source_relative_path' => $filename,
            'file_extension' => 'xlsx',
            'file_size_bytes' => strlen($content),
            'file_hash' => hash('sha256', $content),
            'parsed_document_number' => '273.101.202',
            'parsed_revision_number' => '02',
            'role_hint' => 'master',
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'word_file_path',
            'decision' => 'READY_TO_ATTACH',
            'is_selected' => true,
        ]);

        $result = $this->installer->installItem($item);

        $this->assertTrue($result->isSuccess);
        $this->assertStringStartsWith('geomin-documents/word/', $result->installedPath);
        $this->assertStringEndsWith('.xlsx', $result->installedPath);

        $rev->refresh();
        $this->assertSame($result->installedPath, $rev->word_file_path);
    }

    public function test_rejects_pdf_for_form_f(): void
    {
        $doc = Document::create([
            'document_number' => '273.101.203',
            'title' => 'Formulir PDF Ditolak',
            'category_id' => $this->fCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273101203_rev01.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $content = 'PDF for Form';
        $filename = '273.101.203 Form Rev 01.pdf';
        $this->createSourceFile($filename, $content);

        $batch = $this->createBatch();
        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => $filename,
            'source_relative_path' => $filename,
            'file_extension' => 'pdf',
            'file_size_bytes' => strlen($content),
            'file_hash' => hash('sha256', $content),
            'parsed_document_number' => '273.101.203',
            'parsed_revision_number' => '01',
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'READY_TO_ATTACH',
            'is_selected' => true,
        ]);

        $result = $this->installer->installItem($item);

        $this->assertFalse($result->isSuccess);
        $this->assertSame(InstallItemResult::STATUS_TARGET_INVALID, $result->status);
        $this->assertStringContainsString('Berkas PDF tidak diizinkan untuk kategori Formulir', $result->errorMessage);
    }

    public function test_rejects_pm_category(): void
    {
        $doc = Document::create([
            'document_number' => '273.103',
            'title' => 'Panduan Mutu',
            'category_id' => $this->pmCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273103_rev01.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $content = 'PM Content';
        $filename = '273.103 Panduan Mutu Rev 01.pdf';
        $this->createSourceFile($filename, $content);

        $batch = $this->createBatch();
        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => $filename,
            'source_relative_path' => $filename,
            'file_extension' => 'pdf',
            'file_size_bytes' => strlen($content),
            'file_hash' => hash('sha256', $content),
            'parsed_document_number' => '273.103',
            'parsed_revision_number' => '01',
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'READY_TO_ATTACH',
            'is_selected' => true,
        ]);

        $result = $this->installer->installItem($item);

        $this->assertFalse($result->isSuccess);
        $this->assertSame(InstallItemResult::STATUS_TARGET_INVALID, $result->status);
        $this->assertStringContainsString('Kategori Panduan Mutu (PM) belum memiliki aturan pemasangan terbukti', $result->errorMessage);
    }

    public function test_rejects_slot_occupied_by_valid_physical_file(): void
    {
        $doc = Document::create([
            'document_number' => '273.104',
            'title' => 'Dokumen Terisi',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        $occupiedPath = 'geomin-documents/existing_real_file.pdf';
        Storage::disk('local')->put($occupiedPath, 'Existing Real Physical File');

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => $occupiedPath,
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $content = 'New file trying to overwrite';
        $filename = '273.104 Uji Rev 01.pdf';
        $this->createSourceFile($filename, $content);

        $batch = $this->createBatch();
        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => $filename,
            'source_relative_path' => $filename,
            'file_extension' => 'pdf',
            'file_size_bytes' => strlen($content),
            'file_hash' => hash('sha256', $content),
            'parsed_document_number' => '273.104',
            'parsed_revision_number' => '01',
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'READY_TO_ATTACH',
            'is_selected' => true,
        ]);

        $result = $this->installer->installItem($item);

        $this->assertFalse($result->isSuccess);
        $this->assertSame(InstallItemResult::STATUS_SLOT_OCCUPIED, $result->status);
        $this->assertStringContainsString('dilarang menimpa', $result->errorMessage);

        // Pastikan file lama tidak tertimpa
        $this->assertSame('Existing Real Physical File', Storage::disk('local')->get($occupiedPath));
    }

    public function test_replaces_verified_seeder_placeholder(): void
    {
        $doc = Document::create([
            'document_number' => '273.105',
            'title' => 'Dokumen Seeder',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273105_rev01.pdf', // Seeder placeholder internal
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $content = 'Real File Replacing Seeder';
        $filename = '273.105 Seeder Rev 01.pdf';
        $this->createSourceFile($filename, $content);

        $batch = $this->createBatch();
        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => $filename,
            'source_relative_path' => $filename,
            'file_extension' => 'pdf',
            'file_size_bytes' => strlen($content),
            'file_hash' => hash('sha256', $content),
            'parsed_document_number' => '273.105',
            'parsed_revision_number' => '01',
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'PLACEHOLDER_REPLACEABLE',
            'is_selected' => true,
        ]);

        $result = $this->installer->installItem($item);

        $this->assertTrue($result->isSuccess);
        $rev->refresh();
        $this->assertNotSame('documents/273105_rev01.pdf', $rev->file_path);
        $this->assertSame($result->installedPath, $rev->file_path);
    }

    public function test_rejects_broken_or_unverified_old_reference(): void
    {
        $doc = Document::create([
            'document_number' => '273.106',
            'title' => 'Dokumen Broken Ref',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'unrecognized/custom_path.pdf', // Bukan placeholder resmi
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $content = 'Content';
        $filename = '273.106 Broken Rev 01.pdf';
        $this->createSourceFile($filename, $content);

        $batch = $this->createBatch();
        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => $filename,
            'source_relative_path' => $filename,
            'file_extension' => 'pdf',
            'file_size_bytes' => strlen($content),
            'file_hash' => hash('sha256', $content),
            'parsed_document_number' => '273.106',
            'parsed_revision_number' => '01',
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'PLACEHOLDER_REPLACEABLE',
            'is_selected' => true,
        ]);

        $result = $this->installer->installItem($item);

        $this->assertFalse($result->isSuccess);
        $this->assertSame(InstallItemResult::STATUS_TARGET_INVALID, $result->status);
        $this->assertStringContainsString('bukan placeholder seeder resmi', $result->errorMessage);
    }

    public function test_rejects_when_source_file_is_missing(): void
    {
        $doc = Document::create([
            'document_number' => '273.107',
            'title' => 'Dokumen Missing Source',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273107_rev01.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $filename = 'non_existent_file.pdf'; // Tidak dibuat di disk

        $batch = $this->createBatch();
        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => $filename,
            'source_relative_path' => $filename,
            'file_extension' => 'pdf',
            'file_size_bytes' => 1024,
            'file_hash' => hash('sha256', 'dummy'),
            'parsed_document_number' => '273.107',
            'parsed_revision_number' => '01',
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'PLACEHOLDER_REPLACEABLE',
            'is_selected' => true,
        ]);

        $result = $this->installer->installItem($item);

        $this->assertFalse($result->isSuccess);
        $this->assertSame(InstallItemResult::STATUS_SOURCE_MISSING, $result->status);
        $this->assertStringContainsString('tidak ditemukan di direktori impor', $result->errorMessage);
    }

    public function test_rejects_when_source_file_changed_after_preview(): void
    {
        $doc = Document::create([
            'document_number' => '273.108',
            'title' => 'Dokumen Modified Source',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273108_rev01.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $filename = '273.108 Modified Rev 01.pdf';
        // Buat file asli di disk dengan konten berbeda
        $this->createSourceFile($filename, 'New Content Modified!');

        $batch = $this->createBatch();
        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => $filename,
            'source_relative_path' => $filename,
            'file_extension' => 'pdf',
            'file_size_bytes' => 999, // Ukuran dicatat berbeda
            'file_hash' => hash('sha256', 'Old Content'), // Hash dicatat berbeda
            'parsed_document_number' => '273.108',
            'parsed_revision_number' => '01',
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'PLACEHOLDER_REPLACEABLE',
            'is_selected' => true,
        ]);

        $result = $this->installer->installItem($item);

        $this->assertFalse($result->isSuccess);
        $this->assertSame(InstallItemResult::STATUS_SOURCE_CHANGED, $result->status);
        $this->assertStringContainsString('Ukuran berkas sumber berubah', $result->errorMessage);
    }

    public function test_compensates_by_deleting_new_target_file_when_db_transaction_fails(): void
    {
        $doc = Document::create([
            'document_number' => '273.109',
            'title' => 'Dokumen Rollback Test',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273109_rev01.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $content = 'Content that will fail on DB';
        $filename = '273.109 Rollback Rev 01.pdf';
        $this->createSourceFile($filename, $content);

        $batch = $this->createBatch();
        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => $filename,
            'source_relative_path' => $filename,
            'file_extension' => 'pdf',
            'file_size_bytes' => strlen($content),
            'file_hash' => hash('sha256', $content),
            'parsed_document_number' => '273.109',
            'parsed_revision_number' => '01',
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'PLACEHOLDER_REPLACEABLE',
            'is_selected' => true,
        ]);

        // Simulasikan kegagalan database saat transaksi update dengan mock event listener atau hook
        DocumentRevision::updating(function () {
            throw new Exception('Simulated Database Deadlock or Failure');
        });

        $result = $this->installer->installItem($item);

        $this->assertFalse($result->isSuccess);
        $this->assertSame(InstallItemResult::STATUS_FAILED, $result->status);
        $this->assertStringContainsString('Gagal memperbarui database saat transaksi', $result->errorMessage);

        // Kompensasi: Verifikasi berkas baru di storage geomin-documents telah dihapus bersih!
        $allStoredFiles = Storage::disk('local')->allFiles('geomin-documents');
        $this->assertEmpty($allStoredFiles, 'Kompensasi rollback WAJIB menghapus berkas target baru jika DB gagal commit.');

        // File sumber di direktori impor tetap aman
        $this->assertFileExists($this->tempSourceDir . DIRECTORY_SEPARATOR . $filename);
    }

    public function test_prevents_double_installation(): void
    {
        $doc = Document::create([
            'document_number' => '273.110',
            'title' => 'Dokumen Double Install',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273110_rev01.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $content = 'Double install test';
        $filename = '273.110 Double Rev 01.pdf';
        $this->createSourceFile($filename, $content);

        $batch = $this->createBatch();
        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => $filename,
            'source_relative_path' => $filename,
            'file_extension' => 'pdf',
            'file_size_bytes' => strlen($content),
            'file_hash' => hash('sha256', $content),
            'parsed_document_number' => '273.110',
            'parsed_revision_number' => '01',
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'PLACEHOLDER_REPLACEABLE',
            'is_selected' => true,
        ]);

        // Pemasangan pertama berhasil
        $res1 = $this->installer->installItem($item);
        $this->assertTrue($res1->isSuccess);

        // Pemasangan kedua dicegah!
        $res2 = $this->installer->installItem($item);
        $this->assertFalse($res2->isSuccess);
        $this->assertSame(InstallItemResult::STATUS_ALREADY_ATTACHED, $res2->status);
        $this->assertStringContainsString('sudah pernah berhasil dipasang sebelumnya', $res2->errorMessage);
    }

    public function test_extract_pdf_text_job_has_after_commit_configured(): void
    {
        $job = new ExtractPdfTextJob(1);

        $this->assertTrue($job->afterCommit, 'ExtractPdfTextJob WAJIB mengaktifkan $afterCommit = true.');
    }

    public function test_rejects_ambiguous_or_unmatched_items(): void
    {
        $batch = $this->createBatch();
        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => 'ambiguous.pdf',
            'source_relative_path' => 'ambiguous.pdf',
            'file_extension' => 'pdf',
            'file_size_bytes' => 1024,
            'file_hash' => hash('sha256', 'a'),
            'decision' => 'AMBIGUOUS_MATCH', // Ambigu tidak boleh dipasang
            'is_selected' => true,
        ]);

        $result = $this->installer->installItem($item);

        $this->assertFalse($result->isSuccess);
        $this->assertSame(InstallItemResult::STATUS_SKIPPED, $result->status);
    }

    public function test_installs_batch_and_updates_batch_summary(): void
    {
        $doc = Document::create([
            'document_number' => '273.111',
            'title' => 'Dokumen Batch',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273111_rev01.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $content = 'Batch Content';
        $filename = '273.111 Batch Rev 01.pdf';
        $this->createSourceFile($filename, $content);

        $batch = $this->createBatch();

        // Item 1: terpilih dan valid
        $item1 = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => $filename,
            'source_relative_path' => $filename,
            'file_extension' => 'pdf',
            'file_size_bytes' => strlen($content),
            'file_hash' => hash('sha256', $content),
            'parsed_document_number' => '273.111',
            'parsed_revision_number' => '01',
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'PLACEHOLDER_REPLACEABLE',
            'is_selected' => true,
        ]);

        // Item 2: tidak terpilih (is_selected = false)
        $item2 = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => 'unselected.pdf',
            'source_relative_path' => 'unselected.pdf',
            'file_extension' => 'pdf',
            'file_size_bytes' => 1024,
            'file_hash' => 'hash-x',
            'decision' => 'READY_TO_ATTACH',
            'is_selected' => false,
        ]);

        $batchResult = $this->installer->installBatch($batch);

        $this->assertSame(1, $batchResult->totalProcessed);
        $this->assertSame(1, $batchResult->totalInstalled);
        $this->assertSame(0, $batchResult->totalFailed);
        $this->assertSame('Completed', $batchResult->batch->status);
        $this->assertNotNull($batchResult->batch->completed_at);
    }

    public function test_rejects_when_source_path_is_path_traversal(): void
    {
        $doc = Document::create([
            'document_number' => '273.114',
            'title' => 'Dokumen Traversal',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273114_rev01.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $batch = $this->createBatch();
        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => 'traversal.pdf',
            'source_relative_path' => '../../traversal.pdf',
            'file_extension' => 'pdf',
            'file_size_bytes' => 1024,
            'file_hash' => hash('sha256', 'dummy'),
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'READY_TO_ATTACH',
            'is_selected' => true,
        ]);

        $result = $this->installer->installItem($item);

        $this->assertFalse($result->isSuccess);
        $this->assertSame(InstallItemResult::STATUS_SOURCE_MISSING, $result->status);
    }

    public function test_rejects_when_extension_mismatches_slot(): void
    {
        $doc = Document::create([
            'document_number' => '273.112',
            'title' => 'Dokumen Mismatch',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273112_rev01.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $content = 'Word file mistakenly routed to PDF slot';
        $filename = '273.112 Mismatch Rev 01.docx';
        $this->createSourceFile($filename, $content);

        $batch = $this->createBatch();
        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => $filename,
            'source_relative_path' => $filename,
            'file_extension' => 'docx',
            'file_size_bytes' => strlen($content),
            'file_hash' => hash('sha256', $content),
            'parsed_document_number' => '273.112',
            'parsed_revision_number' => '01',
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path', // Mismatch: PK Word ke file_path
            'decision' => 'READY_TO_ATTACH',
            'is_selected' => true,
        ]);

        $result = $this->installer->installItem($item);

        $this->assertFalse($result->isSuccess);
        $this->assertSame(InstallItemResult::STATUS_TARGET_INVALID, $result->status);
        $this->assertStringContainsString('hanya boleh dipasang ke slot word_file_path', $result->errorMessage);
    }

    public function test_rejects_when_target_revision_is_missing_from_database(): void
    {
        $doc = Document::create([
            'document_number' => '273.113',
            'title' => 'Dokumen Missing Revision',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273113_rev01.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $content = 'Content';
        $filename = '273.113 Missing Rev 01.pdf';
        $this->createSourceFile($filename, $content);

        $batch = $this->createBatch();
        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => $filename,
            'source_relative_path' => $filename,
            'file_extension' => 'pdf',
            'file_size_bytes' => strlen($content),
            'file_hash' => hash('sha256', $content),
            'parsed_document_number' => '273.113',
            'parsed_revision_number' => '01',
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'READY_TO_ATTACH',
            'is_selected' => true,
        ]);

        // Simulasikan revisi target dihapus dari database setelah preview
        $rev->delete();
        $item->refresh();

        $result = $this->installer->installItem($item);

        $this->assertFalse($result->isSuccess);
        $this->assertSame(InstallItemResult::STATUS_TARGET_INVALID, $result->status);
    }

    public function test_prevents_concurrent_processing_via_atomic_transition(): void
    {
        $batch = $this->createBatch();
        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => 'processing.pdf',
            'source_relative_path' => 'processing.pdf',
            'file_extension' => 'pdf',
            'file_size_bytes' => 1024,
            'file_hash' => hash('sha256', 'a'),
            'decision' => 'READY_TO_ATTACH',
            'install_status' => 'PROCESSING', // Sedang diproses request lain
            'is_selected' => true,
        ]);

        $result = $this->installer->installItem($item);

        $this->assertFalse($result->isSuccess);
        $this->assertSame(InstallItemResult::STATUS_FAILED, $result->status);
        $this->assertStringContainsString('sedang diproses oleh permintaan lain', $result->errorMessage);
    }

    public function test_post_commit_exception_does_not_delete_database_referenced_file(): void
    {
        $doc = Document::create([
            'document_number' => '273.115',
            'title' => 'Dokumen Post Commit',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273115_rev01.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $content = 'Valid Content Post Commit Test';
        $filename = '273.115 Post Commit Rev 01.pdf';
        $this->createSourceFile($filename, $content);

        $batch = $this->createBatch();
        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => $filename,
            'source_relative_path' => $filename,
            'file_extension' => 'pdf',
            'file_size_bytes' => strlen($content),
            'file_hash' => hash('sha256', $content),
            'parsed_document_number' => '273.115',
            'parsed_revision_number' => '01',
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'PLACEHOLDER_REPLACEABLE',
            'is_selected' => true,
        ]);

        // Simulasikan hook afterCommit melempar exception pasca-commit
        DocumentRevision::updating(function () {
            DB::connection('mysql')->afterCommit(function () {
                throw new Exception('Post-commit webhook/notification failure');
            });
        });

        $result = $this->installer->installItem($item);

        // Pemasangan tetap dianggap sukses dengan catatan peringatan
        $this->assertTrue($result->isSuccess);
        $this->assertSame(InstallItemResult::STATUS_SUCCESS, $result->status);

        // Kritis: Berkas target yang SUDAH direferensikan database TIDAK BOLEH dihapus!
        Storage::disk('local')->assertExists($result->installedPath);
        $rev->refresh();
        $this->assertSame($result->installedPath, $rev->file_path);
    }

    public function test_two_different_items_targeting_same_slot_clean_up_loser_file_without_deleting_winner_file(): void
    {
        $doc = Document::create([
            'document_number' => '273.116',
            'title' => 'Dokumen Slot Battle',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273116_rev01.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $contentA = 'File Winner Content';
        $filenameA = '273.116 Winner Rev 01.pdf';
        $this->createSourceFile($filenameA, $contentA);

        $contentB = 'File Loser Content';
        $filenameB = '273.116 Loser Rev 01.pdf';
        $this->createSourceFile($filenameB, $contentB);

        $batch = $this->createBatch();

        // Item A (pemenang)
        $itemA = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => $filenameA,
            'source_relative_path' => $filenameA,
            'file_extension' => 'pdf',
            'file_size_bytes' => strlen($contentA),
            'file_hash' => hash('sha256', $contentA),
            'parsed_document_number' => '273.116',
            'parsed_revision_number' => '01',
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'PLACEHOLDER_REPLACEABLE',
            'is_selected' => true,
        ]);

        // Item B (akan kalah karena slot terisi oleh Item A)
        $itemB = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => $filenameB,
            'source_relative_path' => $filenameB,
            'file_extension' => 'pdf',
            'file_size_bytes' => strlen($contentB),
            'file_hash' => hash('sha256', $contentB),
            'parsed_document_number' => '273.116',
            'parsed_revision_number' => '01',
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'PLACEHOLDER_REPLACEABLE',
            'is_selected' => true,
        ]);

        // 1. Eksekusi Item A terlebih dahulu -> SUKSES
        $resA = $this->installer->installItem($itemA);
        $this->assertTrue($resA->isSuccess);
        $winnerPath = $resA->installedPath;
        Storage::disk('local')->assertExists($winnerPath);

        // 2. Eksekusi Item B berikutnya -> GAGAL karena slot sudah terisi fisik oleh Item A
        $resB = $this->installer->installItem($itemB);
        $this->assertFalse($resB->isSuccess);
        $this->assertSame(InstallItemResult::STATUS_SLOT_OCCUPIED, $resB->status);

        // 3. Verifikasi berkas pemenang (Item A) TETAP UTUH di storage dan database
        Storage::disk('local')->assertExists($winnerPath);
        $rev->refresh();
        $this->assertSame($winnerPath, $rev->file_path);

        // 4. Verifikasi berkas milik Item B dibersihkan
        if ($resB->installedPath) {
            Storage::disk('local')->assertMissing($resB->installedPath);
        }
    }

    public function test_recovers_abandoned_processing_item_when_already_attached_in_db(): void
    {
        $doc = Document::create([
            'document_number' => '273.117',
            'title' => 'Dokumen Recovery Attached',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        $content = 'Content that was attached before crash';
        $storedPath = 'geomin-documents/273-117_rev01_abcd1234.pdf';
        Storage::disk('local')->put($storedPath, $content);

        // Simulasikan database DocumentRevision sudah berhasil menyimpan path
        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => $storedPath,
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $batch = $this->createBatch();
        // Simulasikan item tertinggal di status PROCESSING
        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => '273.117 Rev 01.pdf',
            'source_relative_path' => '273.117 Rev 01.pdf',
            'file_extension' => 'pdf',
            'file_size_bytes' => strlen($content),
            'file_hash' => hash('sha256', $content),
            'parsed_document_number' => '273.117',
            'parsed_revision_number' => '01',
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'PLACEHOLDER_REPLACEABLE',
            'install_status' => 'PROCESSING',
            'is_selected' => true,
        ]);

        // Jalankan pemulihan (dengan force agar tidak menunggu stale timeout)
        $recoverResult = $this->installer->recoverStuckItem($item, staleSeconds: 300, force: true);

        $this->assertTrue($recoverResult->wasRecovered);
        $this->assertSame(\App\Services\DocumentSync\DTO\RecoverItemResult::ACTION_CONFIRMED_ATTACHED, $recoverResult->action);

        $item->refresh();
        $this->assertSame(InstallItemResult::STATUS_SUCCESS, $item->install_status, 'Item tidak boleh direset ke PENDING jika terbukti sudah terpasang di DB.');
        $this->assertSame($storedPath, $item->installed_path);
        $this->assertStringContainsString('terbukti telah berhasil terpasang', $item->error_message);
    }

    public function test_recovers_abandoned_processing_item_when_not_attached_in_db(): void
    {
        $doc = Document::create([
            'document_number' => '273.118',
            'title' => 'Dokumen Recovery Failed',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        // Database masih memiliki placeholder lama (artinya belum sempat di-update saat crash)
        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273118_rev01.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $batch = $this->createBatch();
        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => '273.118 Rev 01.pdf',
            'source_relative_path' => '273.118 Rev 01.pdf',
            'file_extension' => 'pdf',
            'file_size_bytes' => 1024,
            'file_hash' => hash('sha256', 'dummy'),
            'parsed_document_number' => '273.118',
            'parsed_revision_number' => '01',
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'PLACEHOLDER_REPLACEABLE',
            'install_status' => 'PROCESSING',
            'is_selected' => true,
        ]);

        $recoverResult = $this->installer->recoverStuckItem($item, staleSeconds: 300, force: true);

        $this->assertTrue($recoverResult->wasRecovered);
        $this->assertSame(\App\Services\DocumentSync\DTO\RecoverItemResult::ACTION_RESET_FAILED, $recoverResult->action);

        $item->refresh();
        $this->assertSame(InstallItemResult::STATUS_FAILED, $item->install_status);
        $this->assertStringContainsString('direset ke status FAILED', $item->error_message);
    }

    public function test_recovery_does_not_hijack_active_process(): void
    {
        $batch = $this->createBatch();
        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => 'active.pdf',
            'source_relative_path' => 'active.pdf',
            'file_extension' => 'pdf',
            'file_size_bytes' => 1024,
            'file_hash' => hash('sha256', 'dummy'),
            'decision' => 'READY_TO_ATTACH',
            'install_status' => 'PROCESSING',
            'is_selected' => true,
        ]);

        // Jangan force: item baru saja dibuat (diffInSeconds < 300)
        $recoverResult = $this->installer->recoverStuckItem($item, staleSeconds: 300, force: false);

        $this->assertFalse($recoverResult->wasRecovered);
        $this->assertSame(\App\Services\DocumentSync\DTO\RecoverItemResult::ACTION_SKIPPED_ACTIVE, $recoverResult->action);
        $this->assertStringContainsString('mencegah intervensi proses aktif', $recoverResult->message);

        $item->refresh();
        $this->assertSame('PROCESSING', $item->install_status, 'Proses aktif tidak boleh diintervensi.');
    }
}
