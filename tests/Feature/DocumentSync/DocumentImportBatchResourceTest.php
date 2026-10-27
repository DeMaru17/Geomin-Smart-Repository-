<?php

namespace Tests\Feature\DocumentSync;

use App\Filament\Resources\DocumentImportBatches\DocumentImportBatchResource;
use App\Filament\Resources\DocumentImportBatches\Pages\ListDocumentImportBatches;
use App\Filament\Resources\DocumentImportBatches\Pages\ViewDocumentImportBatch;
use App\Filament\Resources\DocumentImportBatches\RelationManagers\ItemsRelationManager;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentImportBatch;
use App\Models\DocumentImportItem;
use App\Models\DocumentRevision;
use App\Models\User;
use App\Services\DocumentSync\DTO\InstallItemResult;
use App\Services\DocumentSync\DTO\ScanResult;
use App\Services\DocumentSync\FolderScannerService;
use Filament\Actions\Action;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentImportBatchResourceTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['mysql', 'mysql_hris'];

    protected User $adminUser;
    protected User $manajemenUser;
    protected User $regularUser;
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

        $this->tempSourceDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gsr_ui_test_' . uniqid();
        File::makeDirectory($this->tempSourceDir, 0755, true);
        config(['document_sync.source_path' => $this->tempSourceDir]);

        $this->adminUser = User::factory()->admin()->create();
        $this->manajemenUser = User::factory()->manajemen()->create();
        $this->regularUser = User::factory()->create(['role' => 'personil']);

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

    protected function createSourceFile(string $relativeFilename, string $content = 'Dummy PDF Content'): string
    {
        $fullPath = $this->tempSourceDir . DIRECTORY_SEPARATOR . $relativeFilename;
        $dir = dirname($fullPath);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
        File::put($fullPath, $content);

        return $fullPath;
    }

    public function test_non_admin_cannot_access_resource(): void
    {
        $this->actingAs($this->regularUser);

        $this->assertFalse(DocumentImportBatchResource::canAccess());

        $this->get(DocumentImportBatchResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_admin_can_access_resource_and_render_pages(): void
    {
        $this->actingAs($this->adminUser);

        $this->assertTrue(DocumentImportBatchResource::canAccess());

        $batch = DocumentImportBatch::create([
            'batch_number' => 'BATCH-TEST-001',
            'scanned_by' => $this->adminUser->id,
            'source_directory' => 'document-import',
            'status' => 'Preview_Ready',
            'total_files_scanned' => 1,
            'total_matched' => 1,
        ]);

        Livewire::test(ListDocumentImportBatches::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$batch]);

        Livewire::test(ViewDocumentImportBatch::class, ['record' => $batch->getKey()])
            ->assertSuccessful();
    }

    public function test_scan_directory_action_creates_batch_and_staging_items(): void
    {
        $this->actingAs($this->adminUser);

        $doc = Document::create([
            'document_number' => '273.201',
            'title' => 'Dokumen Scan UI',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '00',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273201_rev00.pdf',
            'uploader_id' => $this->adminUser->id,
            'status' => 'approved',
        ]);

        $content = 'Scan action test content';
        $filename = '273.201 Dokumen Scan Rev 00.pdf';
        $this->createSourceFile($filename, $content);

        Livewire::test(ListDocumentImportBatches::class)
            ->callAction('scan_directory')
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('document_import_batches', [
            'status' => 'Preview_Ready',
            'total_files_scanned' => 1,
            'total_matched' => 1,
        ]);

        $this->assertDatabaseHas('document_import_items', [
            'source_filename' => $filename,
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'is_selected' => false, // Default seleksi WAJIB false
            'install_status' => 'PENDING',
        ]);
    }

    public function test_partial_scan_sets_partial_status_and_displays_warning(): void
    {
        $this->actingAs($this->adminUser);

        // Buat mock FolderScannerService yang mengembalikan isPartial = true
        $fakeFile = new \App\Services\DocumentSync\DTO\ScannedFileInfo(
            filename: 'partial.pdf',
            relativePath: 'partial.pdf',
            absolutePath: $this->createSourceFile('partial.pdf', 'p'),
            extension: 'pdf',
            sizeBytes: 1024,
            hash: hash('sha256', 'p')
        );

        $mockScanner = $this->createMock(FolderScannerService::class);
        $mockScanner->method('scanDirectory')->willReturn(new ScanResult(
            files: [$fakeFile],
            isPartial: true,
            totalFilesEncountered: 550,
            totalSizeBytes: 1024,
            errors: ['Batas maksimum file terlampaui'],
            errorMessage: null,
            partialReason: ScanResult::REASON_LIMIT_FILES
        ));
        $this->app->instance(FolderScannerService::class, $mockScanner);

        Livewire::test(ListDocumentImportBatches::class)
            ->callAction('scan_directory');

        $this->assertDatabaseHas('document_import_batches', [
            'status' => 'Partial_Scan',
            'total_files_scanned' => 1,
        ]);
    }

    public function test_manual_resolution_resolves_ambiguous_item_with_valid_doc_rev_and_slot(): void
    {
        $this->actingAs($this->adminUser);

        $doc = Document::create([
            'document_number' => '273.202',
            'title' => 'Dokumen Ambigu',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273202_rev01.pdf',
            'uploader_id' => $this->adminUser->id,
            'status' => 'approved',
        ]);

        $batch = DocumentImportBatch::create([
            'batch_number' => 'BATCH-TEST-MANUAL',
            'scanned_by' => $this->adminUser->id,
            'source_directory' => 'document-import',
            'status' => 'Preview_Ready',
        ]);

        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => '273.202 Dokumen Tidak Jelas.pdf',
            'source_relative_path' => '273.202 Dokumen Tidak Jelas.pdf',
            'file_extension' => 'pdf',
            'file_size_bytes' => 1024,
            'file_hash' => hash('sha256', 'ambigu'),
            'decision' => 'AMBIGUOUS_MATCH',
            'match_status' => 'AMBIGUOUS_MATCH',
            'slot_current_status' => 'UNKNOWN',
            'is_selected' => false,
            'install_status' => 'PENDING',
        ]);

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => ViewDocumentImportBatch::class,
        ])
            ->callTableAction('resolve_manual', $item, data: [
                'matched_document_id' => $doc->id,
                'matched_revision_id' => $rev->id,
                'target_slot' => 'file_path',
            ])
            ->assertHasNoTableActionErrors();

        $item->refresh();
        $this->assertSame($doc->id, $item->matched_document_id);
        $this->assertSame($rev->id, $item->matched_revision_id);
        $this->assertSame('file_path', $item->target_slot);
        $this->assertSame('MANUAL_RESOLVED', $item->decision);
        $this->assertSame('MATCHED', $item->match_status);
        $this->assertTrue($item->is_selected);
    }

    public function test_manual_resolution_rejects_invalid_doc_and_rev_combination(): void
    {
        $this->actingAs($this->adminUser);

        $docA = Document::create([
            'document_number' => '273.203',
            'title' => 'Dokumen A',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);
        $docB = Document::create([
            'document_number' => '273.204',
            'title' => 'Dokumen B',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        // Revisi milik Doc B
        $revB = DocumentRevision::create([
            'document_id' => $docB->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273204_rev01.pdf',
            'uploader_id' => $this->adminUser->id,
            'status' => 'approved',
        ]);

        $batch = DocumentImportBatch::create([
            'batch_number' => 'BATCH-TEST-MISMATCH',
            'scanned_by' => $this->adminUser->id,
            'status' => 'Preview_Ready',
        ]);

        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => 'test.pdf',
            'source_relative_path' => 'test.pdf',
            'file_extension' => 'pdf',
            'file_size_bytes' => 1024,
            'file_hash' => hash('sha256', 'mismatch'),
            'decision' => 'AMBIGUOUS_MATCH',
            'is_selected' => false,
            'install_status' => 'PENDING',
        ]);

        // Coba pasangkan Doc A dengan Rev B (bukan milik Doc A)
        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => ViewDocumentImportBatch::class,
        ])
            ->callTableAction('resolve_manual', $item, data: [
                'matched_document_id' => $docA->id,
                'matched_revision_id' => $revB->id,
                'target_slot' => 'file_path',
            ]);

        $item->refresh();
        $this->assertNotSame('MANUAL_RESOLVED', $item->decision, 'Penyelesaian manual harus menolak pasangan dokumen-revisi yang tidak sinkron.');
    }

    public function test_manual_resolution_enforces_category_and_extension_slot_rules(): void
    {
        $this->actingAs($this->adminUser);

        // Kategori Formulir F
        $formDoc = Document::create([
            'document_number' => '273.205',
            'title' => 'Formulir Uji',
            'category_id' => $this->fCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);
        $formRev = DocumentRevision::create([
            'document_id' => $formDoc->id,
            'revision_number' => '00',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273205_rev00.xlsx',
            'uploader_id' => $this->adminUser->id,
            'status' => 'approved',
        ]);

        $batch = DocumentImportBatch::create([
            'batch_number' => 'BATCH-TEST-RULES',
            'scanned_by' => $this->adminUser->id,
            'status' => 'Preview_Ready',
        ]);

        // File PDF tidak boleh dipasang ke Formulir F
        $pdfItem = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => '273.205 Form.pdf',
            'source_relative_path' => '273.205 Form.pdf',
            'file_extension' => 'pdf',
            'file_size_bytes' => 1024,
            'file_hash' => hash('sha256', 'pdf_form'),
            'decision' => 'CATEGORY_INCOMPATIBLE',
            'is_selected' => false,
            'install_status' => 'PENDING',
        ]);

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => ViewDocumentImportBatch::class,
        ])
            ->callTableAction('resolve_manual', $pdfItem, data: [
                'matched_document_id' => $formDoc->id,
                'matched_revision_id' => $formRev->id,
                'target_slot' => 'file_path',
            ]);

        $pdfItem->refresh();
        $this->assertNotSame('MANUAL_RESOLVED', $pdfItem->decision, 'PDF dilarang dipasang ke kategori Formulir F.');
    }

    public function test_manual_resolution_rejects_slot_occupied_by_physical_file(): void
    {
        $this->actingAs($this->adminUser);

        $doc = Document::create([
            'document_number' => '273.206',
            'title' => 'Dokumen Slot Terisi',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        $realFile = 'geomin-documents/existing_file.pdf';
        Storage::disk('local')->put($realFile, 'Existing Physical File');

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => $realFile,
            'uploader_id' => $this->adminUser->id,
            'status' => 'approved',
        ]);

        $batch = DocumentImportBatch::create([
            'batch_number' => 'BATCH-TEST-OCCUPIED',
            'scanned_by' => $this->adminUser->id,
            'status' => 'Preview_Ready',
        ]);

        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => '273.206 Dokumen.pdf',
            'source_relative_path' => '273.206 Dokumen.pdf',
            'file_extension' => 'pdf',
            'file_size_bytes' => 1024,
            'file_hash' => hash('sha256', 'occupied'),
            'decision' => 'AMBIGUOUS_MATCH',
            'is_selected' => false,
            'install_status' => 'PENDING',
        ]);

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => ViewDocumentImportBatch::class,
        ])
            ->callTableAction('resolve_manual', $item, data: [
                'matched_document_id' => $doc->id,
                'matched_revision_id' => $rev->id,
                'target_slot' => 'file_path',
            ]);

        $item->refresh();
        $this->assertNotSame('MANUAL_RESOLVED', $item->decision, 'Slot yang sudah berisi berkas fisik dilarang ditimpa melalui manual resolve.');
    }

    public function test_install_action_calls_installer_and_attaches_file(): void
    {
        $this->actingAs($this->adminUser);

        $doc = Document::create([
            'document_number' => '273.207',
            'title' => 'Dokumen Pasang UI',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273207_rev01.pdf', // Seeder placeholder
            'uploader_id' => $this->adminUser->id,
            'status' => 'approved',
        ]);

        $content = 'Install Action Test Content';
        $filename = '273.207 Install Rev 01.pdf';
        $this->createSourceFile($filename, $content);

        $batch = DocumentImportBatch::create([
            'batch_number' => 'BATCH-TEST-INSTALL',
            'scanned_by' => $this->adminUser->id,
            'status' => 'Preview_Ready',
        ]);

        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => $filename,
            'source_relative_path' => $filename,
            'file_extension' => 'pdf',
            'file_size_bytes' => strlen($content),
            'file_hash' => hash('sha256', $content),
            'parsed_document_number' => '273.207',
            'parsed_revision_number' => '01',
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'PLACEHOLDER_REPLACEABLE',
            'is_selected' => true,
            'install_status' => 'PENDING',
        ]);

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => ViewDocumentImportBatch::class,
        ])
            ->callTableAction('install', $item)
            ->assertHasNoTableActionErrors();

        $item->refresh();
        $this->assertSame(InstallItemResult::STATUS_SUCCESS, $item->install_status);
        $this->assertNotNull($item->installed_path);

        $rev->refresh();
        $this->assertSame($item->installed_path, $rev->file_path);
        Storage::disk('local')->assertExists($item->installed_path);
    }

    public function test_repeated_install_on_already_attached_slot_is_prevented(): void
    {
        $this->actingAs($this->adminUser);

        $doc = Document::create([
            'document_number' => '273.208',
            'title' => 'Dokumen Double Install',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273208_rev01.pdf',
            'uploader_id' => $this->adminUser->id,
            'status' => 'approved',
        ]);

        $content = 'Double Install Test Content';
        $filename = '273.208 Double Rev 01.pdf';
        $this->createSourceFile($filename, $content);

        $batch = DocumentImportBatch::create([
            'batch_number' => 'BATCH-TEST-DOUBLE',
            'scanned_by' => $this->adminUser->id,
            'status' => 'Preview_Ready',
        ]);

        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => $filename,
            'source_relative_path' => $filename,
            'file_extension' => 'pdf',
            'file_size_bytes' => strlen($content),
            'file_hash' => hash('sha256', $content),
            'parsed_document_number' => '273.208',
            'parsed_revision_number' => '01',
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'PLACEHOLDER_REPLACEABLE',
            'is_selected' => true,
            'install_status' => 'PENDING',
        ]);

        $component = Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => ViewDocumentImportBatch::class,
        ]);

        // Pemasangan pertama: Sukses
        $component->callTableAction('install', $item);
        $item->refresh();
        $this->assertSame('ATTACHED', $item->install_status);

        // Pemasangan kedua: Tombol aksi install tersembunyi karena item sudah ATTACHED
        $component->assertTableActionHidden('install', $item);
    }

    public function test_install_workflow_does_not_modify_document_approval_status(): void
    {
        $this->actingAs($this->adminUser);

        $doc = Document::create([
            'document_number' => '273.209',
            'title' => 'Dokumen Status Invariant',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273209_rev01.pdf',
            'uploader_id' => $this->adminUser->id,
            'status' => 'approved',
        ]);

        $content = 'Approval Invariant Content';
        $filename = '273.209 Approval Rev 01.pdf';
        $this->createSourceFile($filename, $content);

        $batch = DocumentImportBatch::create([
            'batch_number' => 'BATCH-TEST-INVARIANT',
            'scanned_by' => $this->adminUser->id,
            'status' => 'Preview_Ready',
        ]);

        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => $filename,
            'source_relative_path' => $filename,
            'file_extension' => 'pdf',
            'file_size_bytes' => strlen($content),
            'file_hash' => hash('sha256', $content),
            'parsed_document_number' => '273.209',
            'parsed_revision_number' => '01',
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'PLACEHOLDER_REPLACEABLE',
            'is_selected' => true,
            'install_status' => 'PENDING',
        ]);

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => ViewDocumentImportBatch::class,
        ])->callTableAction('install', $item);

        // Verifikasi alur approval dokumen dan revisi TIDAK BERUBAH
        $doc->refresh();
        $rev->refresh();
        $this->assertSame('approved', $doc->status, 'Status dokumen tidak boleh berubah saat pemasangan file.');
        $this->assertSame('approved', $rev->status, 'Status revisi tidak boleh berubah saat pemasangan file.');
    }

    public function test_unauthorized_user_is_forbidden_from_direct_action_execution(): void
    {
        $this->actingAs($this->regularUser);

        $doc = Document::create([
            'document_number' => '273.901',
            'title' => 'Dokumen Auth Test',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);
        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273901_rev01.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $batch = DocumentImportBatch::create([
            'batch_number' => 'BATCH-TEST-AUTH',
            'scanned_by' => $this->adminUser->id,
            'status' => 'Preview_Ready',
        ]);

        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => 'auth_test.pdf',
            'source_relative_path' => 'auth_test.pdf',
            'file_extension' => 'pdf',
            'file_size_bytes' => 1024,
            'file_hash' => hash('sha256', 'auth'),
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'PLACEHOLDER_REPLACEABLE',
            'is_selected' => true,
            'install_status' => 'PENDING',
        ]);

        // 1. Otorisasi Gate level Policy
        $this->assertFalse(Gate::allows('viewAny', DocumentImportBatch::class));
        $this->assertFalse(Gate::allows('view', $batch));
        $this->assertFalse(Gate::allows('create', DocumentImportBatch::class));
        $this->assertFalse(Gate::allows('update', $batch));
        $this->assertFalse(Gate::allows('delete', $batch));
        $this->assertFalse(Gate::allows('viewAny', DocumentImportItem::class));
        $this->assertFalse(Gate::allows('view', $item));
        $this->assertFalse(Gate::allows('install', $item));
        $this->assertFalse(Gate::allows('recover', $item));
        $this->assertFalse(Gate::allows('update', $item));

        // 2. Akses halaman List & Resource ditolak (403)
        $this->assertFalse(DocumentImportBatchResource::canAccess());
        Livewire::test(ListDocumentImportBatches::class)
            ->assertForbidden();

        // 3. Akses halaman View ditolak (403)
        Livewire::test(ViewDocumentImportBatch::class, ['record' => $batch->getKey()])
            ->assertForbidden();

        // 4. Akses RelationManager ditolak
        $this->assertFalse(ItemsRelationManager::canViewForRecord($batch, ViewDocumentImportBatch::class));
    }

    public function test_manajemen_role_is_authorized_to_access_and_execute_actions(): void
    {
        $this->actingAs($this->manajemenUser);

        $this->assertTrue(DocumentImportBatchResource::canAccess());

        $batch = DocumentImportBatch::create([
            'batch_number' => 'BATCH-TEST-MANAJEMEN',
            'scanned_by' => $this->manajemenUser->id,
            'status' => 'Preview_Ready',
        ]);

        Livewire::test(ListDocumentImportBatches::class)
            ->assertSuccessful();

        Livewire::test(ViewDocumentImportBatch::class, ['record' => $batch->getKey()])
            ->assertSuccessful();
    }

    public function test_form_f_manual_resolution_and_installation_enforces_excel_file_path_and_word_word_file_path(): void
    {
        $this->actingAs($this->adminUser);

        $formDoc = Document::create([
            'document_number' => '273.902',
            'title' => 'Formulir Uji Lengkap',
            'category_id' => $this->fCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        $formRev = DocumentRevision::create([
            'document_id' => $formDoc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273902_rev01.pdf',
            'word_file_path' => null,
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $batch = DocumentImportBatch::create([
            'batch_number' => 'BATCH-TEST-FORM-F',
            'scanned_by' => $this->adminUser->id,
            'status' => 'Preview_Ready',
        ]);

        // A. File Excel Formulir:
        $excelContent = 'Excel Form Content';
        $excelFilename = '273.902 Form Rev 01.xlsx';
        $this->createSourceFile($excelFilename, $excelContent);

        $excelItem = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => $excelFilename,
            'source_relative_path' => $excelFilename,
            'file_extension' => 'xlsx',
            'file_size_bytes' => strlen($excelContent),
            'file_hash' => hash('sha256', $excelContent),
            'decision' => 'AMBIGUOUS_MATCH',
            'is_selected' => false,
            'install_status' => 'PENDING',
        ]);

        $component = Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => ViewDocumentImportBatch::class,
        ]);

        // 1. Coba pasang Excel Formulir ke word_file_path -> WAJIB DITOLAK
        $component->callTableAction('resolve_manual', $excelItem, data: [
            'matched_document_id' => $formDoc->id,
            'matched_revision_id' => $formRev->id,
            'target_slot' => 'word_file_path',
        ]);
        $excelItem->refresh();
        $this->assertNotSame('MANUAL_RESOLVED', $excelItem->decision, 'Excel Formulir dilarang dipasang ke word_file_path.');

        // 2. Pasang Excel Formulir ke file_path -> BERHASIL
        $component->callTableAction('resolve_manual', $excelItem, data: [
            'matched_document_id' => $formDoc->id,
            'matched_revision_id' => $formRev->id,
            'target_slot' => 'file_path',
        ]);
        $excelItem->refresh();
        $this->assertSame('MANUAL_RESOLVED', $excelItem->decision);
        $this->assertSame('file_path', $excelItem->target_slot);
        $this->assertSame('blanko', $excelItem->role_hint);

        // 3. Eksekusi pemasangan Excel Formulir F -> Masuk ke file_path
        $component->callTableAction('install', $excelItem);
        $excelItem->refresh();
        $formRev->refresh();
        $this->assertSame(InstallItemResult::STATUS_SUCCESS, $excelItem->install_status);
        $this->assertSame($excelItem->installed_path, $formRev->file_path);
        $this->assertStringEndsWith('.xlsx', $formRev->file_path);

        // B. File Word Formulir:
        $wordContent = 'Word Form Content';
        $wordFilename = '273.902 Form Master Rev 01.docx';
        $this->createSourceFile($wordFilename, $wordContent);

        $wordItem = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => $wordFilename,
            'source_relative_path' => $wordFilename,
            'file_extension' => 'docx',
            'file_size_bytes' => strlen($wordContent),
            'file_hash' => hash('sha256', $wordContent),
            'decision' => 'AMBIGUOUS_MATCH',
            'is_selected' => false,
            'install_status' => 'PENDING',
        ]);

        // 4. Coba pasang Word Formulir ke file_path -> WAJIB DITOLAK
        $component->callTableAction('resolve_manual', $wordItem, data: [
            'matched_document_id' => $formDoc->id,
            'matched_revision_id' => $formRev->id,
            'target_slot' => 'file_path',
        ]);
        $wordItem->refresh();
        $this->assertNotSame('MANUAL_RESOLVED', $wordItem->decision, 'Word Formulir dilarang dipasang ke file_path.');

        // 5. Pasang Word Formulir ke word_file_path -> BERHASIL
        $component->callTableAction('resolve_manual', $wordItem, data: [
            'matched_document_id' => $formDoc->id,
            'matched_revision_id' => $formRev->id,
            'target_slot' => 'word_file_path',
        ]);
        $wordItem->refresh();
        $this->assertSame('MANUAL_RESOLVED', $wordItem->decision);
        $this->assertSame('word_file_path', $wordItem->target_slot);
        $this->assertSame('master', $wordItem->role_hint);

        // 6. Eksekusi pemasangan Word Formulir F -> Masuk ke word_file_path
        $component->callTableAction('install', $wordItem);
        $wordItem->refresh();
        $formRev->refresh();
        $this->assertSame(InstallItemResult::STATUS_SUCCESS, $wordItem->install_status);
        $this->assertSame($wordItem->installed_path, $formRev->word_file_path);
        $this->assertStringEndsWith('.docx', $formRev->word_file_path);
    }

    public function test_pm_manual_resolution_enforces_slot_and_preserves_safety(): void
    {
        $this->actingAs($this->adminUser);

        $pmDoc = Document::create([
            'document_number' => '273.903',
            'title' => 'Panduan Mutu Uji',
            'category_id' => $this->pmCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);
        $pmRev = DocumentRevision::create([
            'document_id' => $pmDoc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273903_rev01.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $batch = DocumentImportBatch::create([
            'batch_number' => 'BATCH-TEST-PM',
            'scanned_by' => $this->adminUser->id,
            'status' => 'Preview_Ready',
        ]);

        $content = 'PM Test Content';
        $filename = '273.903 PM Rev 01.pdf';
        $this->createSourceFile($filename, $content);

        $pmItem = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => $filename,
            'source_relative_path' => $filename,
            'file_extension' => 'pdf',
            'file_size_bytes' => strlen($content),
            'file_hash' => hash('sha256', $content),
            'decision' => 'MANUAL_REVIEW_REQUIRED',
            'is_selected' => false,
            'install_status' => 'PENDING',
        ]);

        $component = Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => ViewDocumentImportBatch::class,
        ]);

        // Coba pasang PDF PM ke word_file_path -> Ditolak
        $component->callTableAction('resolve_manual', $pmItem, data: [
            'matched_document_id' => $pmDoc->id,
            'matched_revision_id' => $pmRev->id,
            'target_slot' => 'word_file_path',
        ]);
        $pmItem->refresh();
        $this->assertNotSame('MANUAL_RESOLVED', $pmItem->decision);

        // Pasang PDF PM ke file_path -> Valid resolve
        $component->callTableAction('resolve_manual', $pmItem, data: [
            'matched_document_id' => $pmDoc->id,
            'matched_revision_id' => $pmRev->id,
            'target_slot' => 'file_path',
        ]);
        $pmItem->refresh();
        $this->assertSame('MANUAL_RESOLVED', $pmItem->decision);
        $this->assertSame('file_path', $pmItem->target_slot);

        // Eksekusi installer pada PM -> Ditolak oleh DocumentInstallerService demi keamanan data
        $component->callTableAction('install', $pmItem);
        $pmItem->refresh();
        $this->assertSame(InstallItemResult::STATUS_TARGET_INVALID, $pmItem->install_status);
        $this->assertStringContainsString('Kategori Panduan Mutu (PM) belum memiliki aturan pemasangan terbukti', $pmItem->error_message);
    }

    public function test_external_document_manual_resolution_accepts_pdf_and_rejects_word(): void
    {
        $this->actingAs($this->adminUser);

        $extDoc = Document::create([
            'document_number' => 'EXT.904',
            'title' => 'Standar SNI Eksternal',
            'is_external' => true,
            'status' => 'approved',
        ]);
        $extRev = DocumentRevision::create([
            'document_id' => $extDoc->id,
            'revision_number' => '00',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/ext904_rev00.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $batch = DocumentImportBatch::create([
            'batch_number' => 'BATCH-TEST-EXT',
            'scanned_by' => $this->adminUser->id,
            'status' => 'Preview_Ready',
        ]);

        // File Word untuk dokumen eksternal -> Wajib ditolak
        $wordItem = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => 'SNI.docx',
            'source_relative_path' => 'SNI.docx',
            'file_extension' => 'docx',
            'file_size_bytes' => 1024,
            'file_hash' => hash('sha256', 'ext_word'),
            'decision' => 'CATEGORY_INCOMPATIBLE',
            'is_selected' => false,
            'install_status' => 'PENDING',
        ]);

        $component = Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => ViewDocumentImportBatch::class,
        ]);

        $component->callTableAction('resolve_manual', $wordItem, data: [
            'matched_document_id' => $extDoc->id,
            'matched_revision_id' => $extRev->id,
            'target_slot' => 'word_file_path',
        ]);
        $wordItem->refresh();
        $this->assertNotSame('MANUAL_RESOLVED', $wordItem->decision, 'Dokumen eksternal hanya menerima berkas PDF.');
    }

    public function test_manual_resolution_rejects_slot_already_attached_by_another_item(): void
    {
        $this->actingAs($this->adminUser);

        $doc = Document::create([
            'document_number' => '273.905',
            'title' => 'Dokumen Target Conflict',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);
        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'geomin-documents/installed.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $batch = DocumentImportBatch::create([
            'batch_number' => 'BATCH-TEST-CONFLICT',
            'scanned_by' => $this->adminUser->id,
            'status' => 'Preview_Ready',
        ]);

        // Item A sudah ATTACHED pada rev ini
        DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => 'item_a.pdf',
            'source_relative_path' => 'item_a.pdf',
            'file_extension' => 'pdf',
            'file_size_bytes' => 1024,
            'file_hash' => hash('sha256', 'a'),
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'install_status' => InstallItemResult::STATUS_SUCCESS,
        ]);

        // Item B mencoba resolve manual ke target yang sama
        $itemB = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => 'item_b.pdf',
            'source_relative_path' => 'item_b.pdf',
            'file_extension' => 'pdf',
            'file_size_bytes' => 1024,
            'file_hash' => hash('sha256', 'b'),
            'decision' => 'AMBIGUOUS_MATCH',
            'install_status' => 'PENDING',
        ]);

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => ViewDocumentImportBatch::class,
        ])
            ->callTableAction('resolve_manual', $itemB, data: [
                'matched_document_id' => $doc->id,
                'matched_revision_id' => $rev->id,
                'target_slot' => 'file_path',
            ]);

        $itemB->refresh();
        $this->assertNotSame('MANUAL_RESOLVED', $itemB->decision, 'Penyelesaian manual dilarang merebut slot yang sudah berstatus ATTACHED oleh berkas lain.');
    }

    public function test_cannot_resolve_or_reinstall_attached_or_processing_items(): void
    {
        $this->actingAs($this->adminUser);

        $doc = Document::create([
            'document_number' => '273.906',
            'title' => 'Dokumen Attached Test',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);
        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273906_rev01.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $batch = DocumentImportBatch::create([
            'batch_number' => 'BATCH-TEST-STATUSES',
            'scanned_by' => $this->adminUser->id,
            'status' => 'Preview_Ready',
        ]);

        $attachedItem = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => 'attached.pdf',
            'source_relative_path' => 'attached.pdf',
            'file_extension' => 'pdf',
            'file_size_bytes' => 1024,
            'file_hash' => hash('sha256', 'att'),
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'READY_TO_ATTACH',
            'is_selected' => false,
            'install_status' => InstallItemResult::STATUS_SUCCESS,
        ]);

        $processingItem = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => 'processing.pdf',
            'source_relative_path' => 'processing.pdf',
            'file_extension' => 'pdf',
            'file_size_bytes' => 1024,
            'file_hash' => hash('sha256', 'proc'),
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'READY_TO_ATTACH',
            'is_selected' => false,
            'install_status' => InstallItemResult::STATUS_PROCESSING,
        ]);

        $component = Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => ViewDocumentImportBatch::class,
        ]);

        // Pastikan aksi resolve_manual dan install tersembunyi untuk ATTACHED
        $component->assertTableActionHidden('resolve_manual', $attachedItem);
        $component->assertTableActionHidden('install', $attachedItem);

        // Pastikan aksi resolve_manual dan install tersembunyi untuk PROCESSING
        $component->assertTableActionHidden('resolve_manual', $processingItem);
        $component->assertTableActionHidden('install', $processingItem);
        // Namun aksi recover terlihat untuk PROCESSING
        $component->assertTableActionVisible('recover', $processingItem);
    }

    public function test_bulk_installation_with_mixed_results_and_atomic_stats(): void
    {
        $this->actingAs($this->adminUser);

        $doc = Document::create([
            'document_number' => '273.907',
            'title' => 'Dokumen Mixed Bulk',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);
        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273907_rev01.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        // File 1: Valid & Selected -> Berhasil
        $content1 = 'File 1 Content';
        $filename1 = '273.907 Rev 01 Valid.pdf';
        $this->createSourceFile($filename1, $content1);

        $batch = DocumentImportBatch::create([
            'batch_number' => 'BATCH-TEST-MIXED',
            'scanned_by' => $this->adminUser->id,
            'status' => 'Preview_Ready',
            'total_files_scanned' => 4,
        ]);

        $item1 = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => $filename1,
            'source_relative_path' => $filename1,
            'file_extension' => 'pdf',
            'file_size_bytes' => strlen($content1),
            'file_hash' => hash('sha256', $content1),
            'parsed_document_number' => '273.907',
            'parsed_revision_number' => '01',
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'PLACEHOLDER_REPLACEABLE',
            'is_selected' => true,
            'install_status' => 'PENDING',
        ]);

        // File 2: Valid tapi Belum Dipilih (is_selected = false) -> Dilewati
        $item2 = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => 'unselected.pdf',
            'source_relative_path' => 'unselected.pdf',
            'file_extension' => 'pdf',
            'file_size_bytes' => 1024,
            'file_hash' => hash('sha256', 'unsel'),
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'PLACEHOLDER_REPLACEABLE',
            'is_selected' => false,
            'install_status' => 'PENDING',
        ]);

        // File 3: Selected tapi Target Tidak Valid (Word dipasang ke PK file_path) -> Gagal
        $content3 = 'Invalid Target Content';
        $filename3 = 'invalid.docx';
        $this->createSourceFile($filename3, $content3);

        $item3 = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => $filename3,
            'source_relative_path' => $filename3,
            'file_extension' => 'docx',
            'file_size_bytes' => strlen($content3),
            'file_hash' => hash('sha256', $content3),
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path', // Word dilarang ke file_path PK!
            'decision' => 'READY_TO_ATTACH',
            'is_selected' => true,
            'install_status' => 'PENDING',
        ]);

        // File 4: Sudah ATTACHED -> Tidak diproses ulang
        $item4 = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => 'already.pdf',
            'source_relative_path' => 'already.pdf',
            'file_extension' => 'pdf',
            'file_size_bytes' => 1024,
            'file_hash' => hash('sha256', 'att4'),
            'matched_document_id' => $doc->id,
            'matched_revision_id' => $rev->id,
            'target_slot' => 'file_path',
            'decision' => 'READY_TO_ATTACH',
            'is_selected' => true,
            'install_status' => InstallItemResult::STATUS_SUCCESS,
        ]);

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => ViewDocumentImportBatch::class,
        ])->callTableBulkAction('install_selected_bulk', [$item1, $item2, $item3, $item4]);

        $item1->refresh();
        $item2->refresh();
        $item3->refresh();
        $item4->refresh();
        $batch->refresh();

        $this->assertSame(InstallItemResult::STATUS_SUCCESS, $item1->install_status, 'Item terpilih dan valid harus berhasil terpasang.');
        $this->assertSame('PENDING', $item2->install_status, 'Item yang tidak dipilih tidak boleh terpasang.');
        $this->assertSame(InstallItemResult::STATUS_TARGET_INVALID, $item3->install_status, 'Item tidak valid harus berstatus TARGET_INVALID.');
        $this->assertSame(InstallItemResult::STATUS_SUCCESS, $item4->install_status, 'Item yang sudah terpasang tetap ATTACHED.');

        // Konsistensi Counter Batch
        $this->assertSame(2, $batch->total_installed, 'Total installed batch harus mencatat item 1 dan item 4.');
        $this->assertSame(1, $batch->total_failed, 'Total failed batch harus mencatat item 3.');
    }
}

