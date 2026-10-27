<?php

namespace Tests\Feature\DocumentSync;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentRevision;
use App\Services\DocumentSync\DTO\ParsedFileInfo;
use App\Services\DocumentSync\DocumentMatcherService;
use App\Services\DocumentSync\PlaceholderDetectorService;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentMatcherServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected DocumentMatcherService $matcher;
    protected PlaceholderDetectorService $detector;
    protected DocumentCategory $pkCategory;
    protected DocumentCategory $ikCategory;
    protected DocumentCategory $fCategory;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', ['--database' => 'mysql']);

        Storage::fake('local');
        config(['document_sync.disk' => 'local']);

        $this->detector = new PlaceholderDetectorService();
        $this->matcher = new DocumentMatcherService($this->detector);

        $this->pkCategory = DocumentCategory::firstOrCreate(['code' => 'PK'], ['name' => 'Prosedur Kerja']);
        $this->ikCategory = DocumentCategory::firstOrCreate(['code' => 'IK'], ['name' => 'Instruksi Kerja']);
        $this->fCategory = DocumentCategory::firstOrCreate(['code' => 'F'], ['name' => 'Formulir']);
    }

    public function test_matches_single_candidate_for_pk_pdf(): void
    {
        $doc = Document::create([
            'document_number' => '273.101',
            'title' => 'Preparasi Sampel',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '15',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273101_rev15.pdf',
            'word_file_path' => null,
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $parsed = new ParsedFileInfo(
            filename: '273.101 Preparasi Sampel Rev 15.pdf',
            relativePath: '273.101 Preparasi Sampel Rev 15.pdf',
            extension: 'pdf',
            sizeBytes: 1024,
            hash: 'hash-abc',
            documentNumber: '273.101',
            revisionNumber: '15',
            roleHint: null,
            parseStatus: 'PARSED'
        );

        $result = $this->matcher->matchSingle($parsed);

        $this->assertSame('MATCHED', $result->matchStatus);
        $this->assertSame($doc->id, $result->documentId);
        $this->assertSame($rev->id, $result->revisionId);
        $this->assertSame('file_path', $result->targetSlot);
        $this->assertSame('PK', $result->categoryCode);
        $this->assertSame(PlaceholderDetectorService::STATUS_PLACEHOLDER_INTERNAL, $result->slotCurrentStatus);
        $this->assertSame('PLACEHOLDER_REPLACEABLE', $result->decision);
    }

    public function test_unmatched_document_when_document_not_found(): void
    {
        $parsed = new ParsedFileInfo(
            filename: '273.999 Dokumen Gaib Rev 01.pdf',
            relativePath: '273.999 Dokumen Gaib Rev 01.pdf',
            extension: 'pdf',
            sizeBytes: 1024,
            hash: 'hash-abc',
            documentNumber: '273.999',
            revisionNumber: '01',
            roleHint: null,
            parseStatus: 'PARSED'
        );

        $result = $this->matcher->matchSingle($parsed);

        $this->assertSame('DOC_NOT_FOUND', $result->matchStatus);
        $this->assertSame('UNMATCHED_DOCUMENT', $result->decision);
        $this->assertNull($result->documentId);
    }

    public function test_manual_review_when_document_exists_but_revision_not_found(): void
    {
        $doc = Document::create([
            'document_number' => '273.102',
            'title' => 'Pengujian Kimia',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273102_rev01.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $parsed = new ParsedFileInfo(
            filename: '273.102 Pengujian Kimia Rev 99.pdf',
            relativePath: '273.102 Pengujian Kimia Rev 99.pdf',
            extension: 'pdf',
            sizeBytes: 1024,
            hash: 'hash-abc',
            documentNumber: '273.102',
            revisionNumber: '99',
            roleHint: null,
            parseStatus: 'PARSED'
        );

        $result = $this->matcher->matchSingle($parsed);

        $this->assertSame('REV_NOT_FOUND', $result->matchStatus);
        $this->assertSame('MANUAL_REVIEW', $result->decision);
        $this->assertSame($doc->id, $result->documentId);
        $this->assertNull($result->revisionId);
    }

    public function test_flags_ambiguous_when_multiple_revisions_share_same_number(): void
    {
        $doc = Document::create([
            'document_number' => '273.103',
            'title' => 'Pengujian Fisik',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        // Simulasikan 2 revisi dengan nomor revisi identik
        DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '02',
            'effective_date' => '2025-01-01',
            'file_path' => 'documents/273103_rev02_old.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '02',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273103_rev02_new.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $parsed = new ParsedFileInfo(
            filename: '273.103 Pengujian Fisik Rev 02.pdf',
            relativePath: '273.103 Pengujian Fisik Rev 02.pdf',
            extension: 'pdf',
            sizeBytes: 1024,
            hash: 'hash-abc',
            documentNumber: '273.103',
            revisionNumber: '02',
            roleHint: null,
            parseStatus: 'PARSED'
        );

        $result = $this->matcher->matchSingle($parsed);

        $this->assertSame('AMBIGUOUS_REVISION', $result->matchStatus);
        $this->assertSame('AMBIGUOUS_MATCH', $result->decision);
    }

    public function test_slot_occupied_when_target_already_has_physical_file(): void
    {
        $doc = Document::create([
            'document_number' => '273.104',
            'title' => 'Dokumen Terisi Fisik',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $filePath = 'documents/273104_rev05.pdf';
        // Buat file fisik di disk storage
        Storage::disk('local')->put($filePath, 'konten file asli yang sudah ada');

        DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '05',
            'effective_date' => '2026-01-01',
            'file_path' => $filePath,
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $parsed = new ParsedFileInfo(
            filename: '273.104 Dokumen Terisi Fisik Rev 05.pdf',
            relativePath: '273.104 Dokumen Terisi Fisik Rev 05.pdf',
            extension: 'pdf',
            sizeBytes: 1024,
            hash: 'hash-abc',
            documentNumber: '273.104',
            revisionNumber: '05',
            roleHint: null,
            parseStatus: 'PARSED'
        );

        $result = $this->matcher->matchSingle($parsed);

        $this->assertSame('SLOT_OCCUPIED', $result->decision);
        $this->assertSame(PlaceholderDetectorService::STATUS_PHYSICAL_EXISTS, $result->slotCurrentStatus);
        $this->assertStringContainsString('dilarang menimpa', $result->errorMessage);
    }

    public function test_slot_placeholder_replaceable_when_target_has_verified_seeder_placeholder(): void
    {
        $doc = Document::create([
            'document_number' => '273.105',
            'title' => 'Dokumen Placeholder Seeder',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273105_rev01.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $parsed = new ParsedFileInfo(
            filename: '273.105 Dokumen Placeholder Seeder Rev 01.pdf',
            relativePath: '273.105 Dokumen Placeholder Seeder Rev 01.pdf',
            extension: 'pdf',
            sizeBytes: 1024,
            hash: 'hash-abc',
            documentNumber: '273.105',
            revisionNumber: '01',
            roleHint: null,
            parseStatus: 'PARSED'
        );

        $result = $this->matcher->matchSingle($parsed);

        $this->assertSame('PLACEHOLDER_REPLACEABLE', $result->decision);
        $this->assertSame(PlaceholderDetectorService::STATUS_PLACEHOLDER_INTERNAL, $result->slotCurrentStatus);
    }

    public function test_manual_review_when_target_is_broken_reference(): void
    {
        $doc = Document::create([
            'document_number' => '273.106',
            'title' => 'Dokumen Broken Reference',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'some_custom_folder/random_path.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $parsed = new ParsedFileInfo(
            filename: '273.106 Dokumen Broken Reference Rev 01.pdf',
            relativePath: '273.106 Dokumen Broken Reference Rev 01.pdf',
            extension: 'pdf',
            sizeBytes: 1024,
            hash: 'hash-abc',
            documentNumber: '273.106',
            revisionNumber: '01',
            roleHint: null,
            parseStatus: 'PARSED'
        );

        $result = $this->matcher->matchSingle($parsed);

        $this->assertSame('MANUAL_REVIEW', $result->decision);
        $this->assertSame(PlaceholderDetectorService::STATUS_BROKEN_REFERENCE, $result->slotCurrentStatus);
    }

    public function test_maps_pk_ik_pdf_to_file_path_and_docx_to_word_file_path(): void
    {
        $doc = Document::create([
            'document_number' => '273.101.101',
            'title' => 'Instruksi Kerja Pengujian',
            'category_id' => $this->ikCategory->id,
            'is_external' => false,
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '03',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273101101_rev03.pdf',
            'word_file_path' => null,
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        // PDF -> file_path
        $parsedPdf = new ParsedFileInfo(
            filename: '273.101.101 IK Rev 03.pdf',
            relativePath: '273.101.101 IK Rev 03.pdf',
            extension: 'pdf',
            sizeBytes: 1024,
            hash: 'h1',
            documentNumber: '273.101.101',
            revisionNumber: '03',
            roleHint: null,
            parseStatus: 'PARSED'
        );
        $resPdf = $this->matcher->matchSingle($parsedPdf);
        $this->assertSame('file_path', $resPdf->targetSlot);
        $this->assertSame('PLACEHOLDER_REPLACEABLE', $resPdf->decision);

        // DOCX -> word_file_path (word_file_path awalnya null -> READY_TO_ATTACH)
        $parsedDocx = new ParsedFileInfo(
            filename: '273.101.101 IK Rev 03.docx',
            relativePath: '273.101.101 IK Rev 03.docx',
            extension: 'docx',
            sizeBytes: 2048,
            hash: 'h2',
            documentNumber: '273.101.101',
            revisionNumber: '03',
            roleHint: null,
            parseStatus: 'PARSED'
        );
        $resDocx = $this->matcher->matchSingle($parsedDocx);
        $this->assertSame('word_file_path', $resDocx->targetSlot);
        $this->assertSame('READY_TO_ATTACH', $resDocx->decision);
    }

    public function test_maps_form_f_xlsx_blanko_to_file_path_and_docx_to_word_file_path(): void
    {
        $doc = Document::create([
            'document_number' => '273.101.201',
            'title' => 'Formulir Catatan Analisis',
            'category_id' => $this->fCategory->id,
            'is_external' => false,
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => '',
            'word_file_path' => null,
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        // Form F: XLSX dengan roleHint blanko -> file_path
        $parsedBlanko = new ParsedFileInfo(
            filename: '273.101.201 Blanko Analisis Rev 01.xlsx',
            relativePath: '273.101.201 Blanko Analisis Rev 01.xlsx',
            extension: 'xlsx',
            sizeBytes: 1024,
            hash: 'h1',
            documentNumber: '273.101.201',
            revisionNumber: '01',
            roleHint: 'blanko',
            parseStatus: 'PARSED'
        );
        $resBlanko = $this->matcher->matchSingle($parsedBlanko);
        $this->assertSame('file_path', $resBlanko->targetSlot);
        $this->assertSame('READY_TO_ATTACH', $resBlanko->decision);

        // Form F: DOCX -> word_file_path
        $parsedDocx = new ParsedFileInfo(
            filename: '273.101.201 Master Catatan Rev 01.docx',
            relativePath: '273.101.201 Master Catatan Rev 01.docx',
            extension: 'docx',
            sizeBytes: 2048,
            hash: 'h2',
            documentNumber: '273.101.201',
            revisionNumber: '01',
            roleHint: null,
            parseStatus: 'PARSED'
        );
        $resDocx = $this->matcher->matchSingle($parsedDocx);
        $this->assertSame('word_file_path', $resDocx->targetSlot);
        $this->assertSame('READY_TO_ATTACH', $resDocx->decision);
    }

    public function test_rejects_form_f_pdf_as_automatic_blanko(): void
    {
        $doc = Document::create([
            'document_number' => '273.101.202',
            'title' => 'Formulir Uji Fisik',
            'category_id' => $this->fCategory->id,
            'is_external' => false,
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '00',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273101202_rev00.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $parsed = new ParsedFileInfo(
            filename: '273.101.202 Formulir Uji Fisik Rev 00.pdf',
            relativePath: '273.101.202 Formulir Uji Fisik Rev 00.pdf',
            extension: 'pdf',
            sizeBytes: 1024,
            hash: 'hash-f',
            documentNumber: '273.101.202',
            revisionNumber: '00',
            roleHint: null,
            parseStatus: 'PARSED'
        );

        $result = $this->matcher->matchSingle($parsed);

        $this->assertSame('INVALID_EXTENSION', $result->matchStatus);
        $this->assertSame('INVALID_EXTENSION', $result->decision);
        $this->assertNull($result->targetSlot);
        $this->assertStringContainsString('Berkas PDF tidak diizinkan untuk kategori Formulir', $result->errorMessage);
    }

    public function test_detects_conflict_when_two_source_files_target_same_revision_and_slot(): void
    {
        $doc = Document::create([
            'document_number' => '273.107',
            'title' => 'Dokumen Konflik',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273107_rev01.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        // Dua file berbeda menargetkan dokumen dan revisi yang sama (keduanya PDF PK -> file_path)
        $file1 = new ParsedFileInfo(
            filename: '273.107 Versi A Rev 01.pdf',
            relativePath: 'folderA/273.107 Versi A Rev 01.pdf',
            extension: 'pdf',
            sizeBytes: 1024,
            hash: 'h1',
            documentNumber: '273.107',
            revisionNumber: '01',
            roleHint: null,
            parseStatus: 'PARSED'
        );

        $file2 = new ParsedFileInfo(
            filename: '273.107 Versi B Rev 01.pdf',
            relativePath: 'folderB/273.107 Versi B Rev 01.pdf',
            extension: 'pdf',
            sizeBytes: 2048,
            hash: 'h2',
            documentNumber: '273.107',
            revisionNumber: '01',
            roleHint: null,
            parseStatus: 'PARSED'
        );

        $results = $this->matcher->matchBatch([$file1, $file2]);

        $this->assertCount(2, $results);
        $this->assertSame('CONFLICT_DUPLICATE_SOURCE', $results[0]->decision);
        $this->assertSame('CONFLICT_DUPLICATE_SOURCE', $results[0]->matchStatus);
        $this->assertSame('CONFLICT_DUPLICATE_SOURCE', $results[1]->decision);
        $this->assertSame('CONFLICT_DUPLICATE_SOURCE', $results[1]->matchStatus);
        $this->assertStringContainsString('Konflik: Terdapat 2 berkas sumber', $results[0]->errorMessage);
    }

    public function test_handles_storage_unreachable_during_matching(): void
    {
        $doc = Document::create([
            'document_number' => '273.108',
            'title' => 'Dokumen Storage Error',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273108_rev01.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        // Mock detector yang mengembalikan STATUS_STORAGE_UNREACHABLE
        $mockDetector = \Mockery::mock(PlaceholderDetectorService::class);
        $mockDetector->shouldReceive('detectForRevision')
            ->andReturn(PlaceholderDetectorService::STATUS_STORAGE_UNREACHABLE);

        $customMatcher = new DocumentMatcherService($mockDetector);

        $parsed = new ParsedFileInfo(
            filename: '273.108 Storage Error Rev 01.pdf',
            relativePath: '273.108 Storage Error Rev 01.pdf',
            extension: 'pdf',
            sizeBytes: 1024,
            hash: 'h1',
            documentNumber: '273.108',
            revisionNumber: '01',
            roleHint: null,
            parseStatus: 'PARSED'
        );

        $result = $customMatcher->matchSingle($parsed);

        $this->assertSame('MANUAL_REVIEW', $result->decision);
        $this->assertSame(PlaceholderDetectorService::STATUS_STORAGE_UNREACHABLE, $result->slotCurrentStatus);
        $this->assertStringContainsString('Media penyimpanan storage tidak dapat diakses', $result->errorMessage);
    }

    public function test_routes_pm_category_to_manual_review(): void
    {
        $pmCategory = DocumentCategory::firstOrCreate(['code' => 'PM'], ['name' => 'Panduan Mutu']);

        $doc = Document::create([
            'document_number' => '273.109',
            'title' => 'Panduan Mutu Laboratorium',
            'category_id' => $pmCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273109_rev01.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $parsed = new ParsedFileInfo(
            filename: '273.109 Panduan Mutu Rev 01.pdf',
            relativePath: '273.109 Panduan Mutu Rev 01.pdf',
            extension: 'pdf',
            sizeBytes: 1024,
            hash: 'h-pm',
            documentNumber: '273.109',
            revisionNumber: '01',
            roleHint: null,
            parseStatus: 'PARSED'
        );

        $result = $this->matcher->matchSingle($parsed);

        $this->assertSame('MANUAL_REVIEW', $result->decision);
        $this->assertSame('MANUAL_REVIEW', $result->matchStatus);
        $this->assertNull($result->targetSlot);
        $this->assertStringContainsString('Kategori Panduan Mutu (PM) belum memiliki konvensi pemasangan terbukti', $result->errorMessage);
    }

    public function test_matches_revision_stored_as_unpadded_integer_in_database(): void
    {
        $doc = Document::create([
            'document_number' => '273.110',
            'title' => 'Dokumen Revisi Integer',
            'category_id' => $this->pkCategory->id,
            'is_external' => false,
            'status' => 'approved',
        ]);

        // Simulasikan database yang menyimpan revision_number sebagai '1' (tanpa leading zero)
        $rev = DocumentRevision::create([
            'document_id' => $doc->id,
            'revision_number' => '1',
            'effective_date' => '2026-01-01',
            'file_path' => 'documents/273110_rev01.pdf',
            'uploader_id' => 1,
            'status' => 'approved',
        ]);

        $parsed = new ParsedFileInfo(
            filename: '273.110 Dokumen Revisi Integer Rev 01.pdf',
            relativePath: '273.110 Dokumen Revisi Integer Rev 01.pdf',
            extension: 'pdf',
            sizeBytes: 1024,
            hash: 'h-int',
            documentNumber: '273.110',
            revisionNumber: '01',
            roleHint: null,
            parseStatus: 'PARSED'
        );

        $result = $this->matcher->matchSingle($parsed);

        $this->assertSame('MATCHED', $result->matchStatus);
        $this->assertSame($rev->id, $result->revisionId);
        $this->assertSame('file_path', $result->targetSlot);
    }
}
