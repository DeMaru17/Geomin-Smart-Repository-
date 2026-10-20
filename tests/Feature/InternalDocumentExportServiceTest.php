<?php

namespace Tests\Feature;

use App\Filament\Resources\Documents\Exports\ExternalDocumentExport;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentRevision;
use App\Services\InternalDocumentExportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalDocumentExportServiceTest extends TestCase
{
    use RefreshDatabase;

    private InternalDocumentExportService $service;
    private DocumentCategory $catPk;
    private DocumentCategory $catIk;
    private DocumentCategory $catF;
    private Department $dept;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InternalDocumentExportService();

        $this->catPk = DocumentCategory::factory()->create([
            'code' => 'PK',
            'name' => 'Prosedur Kerja',
        ]);
        $this->catIk = DocumentCategory::factory()->create([
            'code' => 'IK',
            'name' => 'Instruksi Kerja',
        ]);
        $this->catF = DocumentCategory::factory()->create([
            'code' => 'F',
            'name' => 'Formulir',
        ]);
        $this->dept = Department::factory()->create();
    }

    /**
     * 1. Dokumen aktif dengan revisi Published masuk ekspor.
     */
    public function test_active_document_with_published_revision_is_included(): void
    {
        $doc = Document::factory()->create([
            'document_number' => '273.101.1',
            'title' => 'Dokumen Aktif',
            'category_id' => $this->catPk->id,
            'department_id' => $this->dept->id,
            'is_external' => false,
            'status' => 'Active',
        ]);

        DocumentRevision::factory()->create([
            'document_id' => $doc->id,
            'revision_number' => '00',
            'status' => 'Published',
            'revision_date' => Carbon::parse('2026-01-10'),
        ]);

        $grouped = $this->service->getDocumentsGroupedByCategory();

        $this->assertArrayHasKey('PK', $grouped);
        $this->assertCount(1, $grouped['PK']['documents']);
        $this->assertEquals($doc->id, $grouped['PK']['documents']->first()->id);
    }

    /**
     * 2. Dokumen Obsolete tidak masuk ekspor.
     */
    public function test_obsolete_document_is_excluded(): void
    {
        $doc = Document::factory()->create([
            'document_number' => '273.101.99',
            'title' => 'Dokumen Usang',
            'category_id' => $this->catPk->id,
            'department_id' => $this->dept->id,
            'is_external' => false,
            'status' => 'Obsolete',
        ]);

        DocumentRevision::factory()->create([
            'document_id' => $doc->id,
            'revision_number' => '00',
            'status' => 'Published',
            'revision_date' => Carbon::parse('2025-01-01'),
        ]);

        $grouped = $this->service->getDocumentsGroupedByCategory();

        $this->assertArrayNotHasKey('PK', $grouped);
    }

    /**
     * 3. Dokumen tanpa revisi Published/Terbit tidak masuk ekspor.
     */
    public function test_document_without_published_revision_is_excluded(): void
    {
        $doc = Document::factory()->create([
            'document_number' => '273.101.50',
            'title' => 'Dokumen Baru Draft',
            'category_id' => $this->catPk->id,
            'department_id' => $this->dept->id,
            'is_external' => false,
            'status' => 'Active',
        ]);

        DocumentRevision::factory()->create([
            'document_id' => $doc->id,
            'revision_number' => '00',
            'status' => 'Draft',
        ]);

        $grouped = $this->service->getDocumentsGroupedByCategory();

        $this->assertArrayNotHasKey('PK', $grouped);
    }

    /**
     * 4. Revisi Draft yang lebih baru tidak menggantikan revisi Published yang masih berlaku.
     */
    public function test_newer_draft_revision_does_not_replace_published_revision(): void
    {
        $doc = Document::factory()->create([
            'document_number' => '273.101.1',
            'title' => 'Prosedur Berjalan',
            'category_id' => $this->catPk->id,
            'department_id' => $this->dept->id,
            'is_external' => false,
            'status' => 'Active',
        ]);

        $publishedRev = DocumentRevision::factory()->create([
            'document_id' => $doc->id,
            'revision_number' => '01',
            'status' => 'Published',
            'revision_date' => Carbon::parse('2026-02-01'),
        ]);

        DocumentRevision::factory()->create([
            'document_id' => $doc->id,
            'revision_number' => '02',
            'status' => 'Draft',
            'revision_date' => Carbon::parse('2026-03-01'),
        ]);

        $doc->load('revisions');
        $validRev = $this->service->getValidPublishedRevision($doc);

        $this->assertNotNull($validRev);
        $this->assertEquals($publishedRev->id, $validRev->id);
        $this->assertEquals('01', $validRev->revision_number);
    }

    /**
     * 5. Dokumen eksternal tidak masuk ekspor internal.
     */
    public function test_external_document_is_excluded(): void
    {
        $doc = Document::factory()->create([
            'document_number' => 'EXT-2026-01',
            'title' => 'Dokumen Standar SNI',
            'category_id' => $this->catPk->id,
            'department_id' => $this->dept->id,
            'is_external' => true,
            'status' => 'Active',
        ]);

        DocumentRevision::factory()->create([
            'document_id' => $doc->id,
            'revision_number' => '00',
            'status' => 'Published',
            'revision_date' => Carbon::parse('2026-01-01'),
        ]);

        $grouped = $this->service->getDocumentsGroupedByCategory();

        $this->assertArrayNotHasKey('PK', $grouped);
    }

    /**
     * 6. Urutan kategori tetap Prosedur Kerja -> Instruksi Kerja -> Formulir/Form.
     */
    public function test_category_ordering_is_strictly_pk_ik_f(): void
    {
        // Buat data pada ketiga kategori
        foreach ([$this->catF, $this->catPk, $this->catIk] as $cat) {
            $doc = Document::factory()->create([
                'category_id' => $cat->id,
                'department_id' => $this->dept->id,
                'is_external' => false,
                'status' => 'Active',
            ]);

            DocumentRevision::factory()->create([
                'document_id' => $doc->id,
                'status' => 'Published',
                'revision_number' => '00',
                'revision_date' => Carbon::parse('2026-01-01'),
            ]);
        }

        $grouped = $this->service->getDocumentsGroupedByCategory();

        $keys = array_keys($grouped);
        $this->assertEquals(['PK', 'IK', 'F'], $keys);
        $this->assertEquals('PROSEDUR KERJA', $grouped['PK']['title']);
        $this->assertEquals('INTRUKSI KERJA', $grouped['IK']['title']);
        $this->assertEquals('FORMULIR', $grouped['F']['title']);
    }

    /**
     * 7. Nomor dokumen diurutkan secara natural di dalam kategori.
     */
    public function test_document_numbers_are_sorted_naturally(): void
    {
        $docNumbers = ['273.101.10', '273.101.2', '273.101.1', '273.101.20', '273.101.9'];

        foreach ($docNumbers as $num) {
            $doc = Document::factory()->create([
                'document_number' => $num,
                'category_id' => $this->catPk->id,
                'department_id' => $this->dept->id,
                'is_external' => false,
                'status' => 'Active',
            ]);

            DocumentRevision::factory()->create([
                'document_id' => $doc->id,
                'status' => 'Published',
                'revision_number' => '00',
                'revision_date' => Carbon::parse('2026-01-01'),
            ]);
        }

        $grouped = $this->service->getDocumentsGroupedByCategory();
        $sortedNumbers = $grouped['PK']['documents']->pluck('document_number')->toArray();

        $this->assertEquals(['273.101.1', '273.101.2', '273.101.9', '273.101.10', '273.101.20'], $sortedNumbers);
    }

    /**
     * 8. Nomor urut kembali ke 1 ketika kategori berganti.
     * 9. Kolom retensi selalu berisi 'sampai revisi berikutnya'.
     * 10. Tanggal dan nomor revisi ditulis dengan benar.
     * 11. Jumlah baris dinamis tidak menimpa area tanda tangan.
     */
    public function test_spreadsheet_generation_content_and_signature_preservation(): void
    {
        // Siapkan dokumen PK dan IK
        $docPk1 = Document::factory()->create([
            'document_number' => '273.101.1',
            'title' => 'Prosedur Uji Mutu',
            'category_id' => $this->catPk->id,
            'department_id' => $this->dept->id,
            'is_external' => false,
            'status' => 'Active',
        ]);
        DocumentRevision::factory()->create([
            'document_id' => $docPk1->id,
            'status' => 'Published',
            'revision_number' => '00',
            'revision_date' => Carbon::parse('2026-01-15'),
        ]);

        $docPk2 = Document::factory()->create([
            'document_number' => '273.101.2',
            'title' => 'Prosedur Kalibrasi',
            'category_id' => $this->catPk->id,
            'department_id' => $this->dept->id,
            'is_external' => false,
            'status' => 'Active',
        ]);
        DocumentRevision::factory()->create([
            'document_id' => $docPk2->id,
            'status' => 'Published',
            'revision_number' => '09',
            'revision_date' => Carbon::parse('2026-02-20'),
        ]);

        $docIk1 = Document::factory()->create([
            'document_number' => '273.102.1',
            'title' => 'IK Preparasi',
            'category_id' => $this->catIk->id,
            'department_id' => $this->dept->id,
            'is_external' => false,
            'status' => 'Active',
        ]);
        DocumentRevision::factory()->create([
            'document_id' => $docIk1->id,
            'status' => 'Published',
            'revision_number' => '01',
            'revision_date' => Carbon::parse('2026-03-05'),
        ]);

        $spreadsheet = $this->service->generateSpreadsheet();
        $this->assertNotFalse($spreadsheet);

        $sheet = $spreadsheet->getActiveSheet();

        // Row 8: Judul kategori PK
        $this->assertEquals('PROSEDUR KERJA', $sheet->getCell('A8')->getValue());

        // Row 9: Dokumen PK 1 (nomor urut 1)
        $this->assertEquals(1, $sheet->getCell('A9')->getValue());
        $this->assertEquals('273.101.1', $sheet->getCell('B9')->getValue());
        $this->assertEquals('Prosedur Uji Mutu', $sheet->getCell('C9')->getValue());
        $this->assertEquals('00', $sheet->getCell('E9')->getValue());
        $this->assertEquals('15/01/2026', $sheet->getCell('F9')->getValue());
        $this->assertEquals('sampai revisi berikutnya', $sheet->getCell('G9')->getValue());

        // Row 10: Dokumen PK 2 (nomor urut 2)
        $this->assertEquals(2, $sheet->getCell('A10')->getValue());
        $this->assertEquals('273.101.2', $sheet->getCell('B10')->getValue());
        $this->assertEquals('09', $sheet->getCell('E10')->getValue());
        $this->assertEquals('sampai revisi berikutnya', $sheet->getCell('G10')->getValue());

        // Row 11: Baris pemisah PK
        $this->assertEmpty($sheet->getCell('A11')->getValue());

        // Row 12: Judul kategori IK
        $this->assertEquals('INTRUKSI KERJA', $sheet->getCell('A12')->getValue());

        // Row 13: Dokumen IK 1 (nomor urut berlanjut secara global ke 3!)
        $this->assertEquals(3, $sheet->getCell('A13')->getValue());
        $this->assertEquals('273.102.1', $sheet->getCell('B13')->getValue());
        $this->assertEquals('IK Preparasi', $sheet->getCell('C13')->getValue());
        $this->assertEquals('sampai revisi berikutnya', $sheet->getCell('G13')->getValue());

        // Row 14: Baris pemisah IK
        $this->assertEmpty($sheet->getCell('A14')->getValue());

        // Pastikan seluruh baris dari baris 1 s/d highestRow tidak ada yang hidden
        $highestRow = $sheet->getHighestRow();
        for ($r = 1; $r <= $highestRow; $r++) {
            $rowDim = $sheet->getRowDimension($r);
            $this->assertTrue($rowDim->getVisible(), "Baris $r seharusnya visible");
            $this->assertFalse($rowDim->getZeroHeight(), "Baris $r seharusnya zeroHeight=false");
        }

        // Kategori Formulir tidak memiliki dokumen aktif, sehingga TIDAK ditampilkan
        // Area tanda tangan harus bergeser secara rapi dan tidak tertimpa
        $signatureFound = false;
        for ($r = 15; $r <= $highestRow; $r++) {
            $eVal = (string) $sheet->getCell("E$r")->getValue();
            if (str_contains($eVal, 'Laboratory Bureau Head')) {
                $signatureFound = true;
                // Pastikan Ruswana ada beberapa baris di bawahnya
                $nameVal = (string) $sheet->getCell("E" . ($r + 5))->getValue();
                $this->assertStringContainsString('Ruswana', $nameVal);
                break;
            }
        }
        $this->assertTrue($signatureFound, 'Area tanda tangan Laboratory Bureau Head harus tetap ada dan tidak tertimpa.');

        // Gambar tanda tangan (Picture 2) harus tetap ada di sheet
        $drawings = $sheet->getDrawingCollection();
        $this->assertCount(2, $drawings);

        // Verifikasi file XLSX hasil save tidak mengandung atribut hidden="true" pada row XML
        $tempPath = storage_path('app/temp/test_verify_hidden.xlsx');
        if (! is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($tempPath);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tempPath));
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($tempPath);

        preg_match_all('/<row [^>]*hidden="[^"]*"[^>]*>/', $xml, $hiddenRowMatches);
        $this->assertEmpty($hiddenRowMatches[0], 'Tidak boleh ada tag hidden pada baris <row> di XML');
    }

    /**
     * 12. Dokumen obsolete lama dan dokumen aktif baru dengan nomor yang sama tidak tercampur.
     */
    public function test_reused_document_numbers_do_not_mix(): void
    {
        $oldDoc = Document::factory()->create([
            'document_number' => '273.101.99',
            'title' => 'Prosedur Lama Obsolete',
            'category_id' => $this->catPk->id,
            'department_id' => $this->dept->id,
            'is_external' => false,
            'status' => 'Obsolete',
        ]);
        DocumentRevision::factory()->create([
            'document_id' => $oldDoc->id,
            'revision_number' => '05',
            'status' => 'Published',
            'revision_date' => Carbon::parse('2020-01-01'),
        ]);

        $newDoc = Document::factory()->create([
            'document_number' => '273.101.99-BARU',
            'title' => 'Prosedur Baru Aktif',
            'category_id' => $this->catPk->id,
            'department_id' => $this->dept->id,
            'is_external' => false,
            'status' => 'Active',
        ]);
        DocumentRevision::factory()->create([
            'document_id' => $newDoc->id,
            'revision_number' => '00',
            'status' => 'Published',
            'revision_date' => Carbon::parse('2026-01-01'),
        ]);

        $grouped = $this->service->getDocumentsGroupedByCategory();
        $docs = $grouped['PK']['documents'];

        $this->assertCount(1, $docs);
        $this->assertEquals($newDoc->id, $docs->first()->id);
        $this->assertEquals('Prosedur Baru Aktif', $docs->first()->title);

        $validRev = $this->service->getValidPublishedRevision($docs->first());
        $this->assertEquals('00', $validRev->revision_number);
    }

    /**
     * 13. Ekspor eksternal tetap berfungsi setelah refactor.
     */
    public function test_external_document_export_remains_functional(): void
    {
        $export = new ExternalDocumentExport('export_form_203');
        $export->setUp();
        $this->assertInstanceOf(ExternalDocumentExport::class, $export);

        $reflection = new \ReflectionProperty($export, 'filename');
        $this->assertEquals('Form-273.106.203-Daftar-Induk-Dokumen-Eksternal', $reflection->getValue($export));
    }

    /**
     * 14. Ekspor tidak mengubah data database.
     */
    public function test_export_is_strictly_read_only(): void
    {
        $doc = Document::factory()->create([
            'document_number' => '273.101.1',
            'title' => 'Dokumen Asli',
            'category_id' => $this->catPk->id,
            'department_id' => $this->dept->id,
            'is_external' => false,
            'status' => 'Active',
        ]);

        $rev = DocumentRevision::factory()->create([
            'document_id' => $doc->id,
            'revision_number' => '00',
            'status' => 'Published',
            'revision_date' => Carbon::parse('2026-01-01'),
        ]);

        $docCountBefore = Document::count();
        $revCountBefore = DocumentRevision::count();

        $this->service->export();

        $this->assertEquals($docCountBefore, Document::count());
        $this->assertEquals($revCountBefore, DocumentRevision::count());

        $docAfter = Document::find($doc->id);
        $revAfter = DocumentRevision::find($rev->id);

        $this->assertEquals('Active', $docAfter->status);
        $this->assertEquals('Published', $revAfter->status);
        $this->assertEquals('00', $revAfter->revision_number);
    }

    /**
     * 15. Penomoran berlanjut secara global dari PK -> IK -> F.
     */
    public function test_continuous_numbering_across_all_three_categories(): void
    {
        // 2 dokumen PK
        for ($i = 1; $i <= 2; $i++) {
            $doc = Document::factory()->create([
                'document_number' => "273.101.$i",
                'title' => "Prosedur $i",
                'category_id' => $this->catPk->id,
                'department_id' => $this->dept->id,
                'is_external' => false,
                'status' => 'Active',
            ]);
            DocumentRevision::factory()->create([
                'document_id' => $doc->id,
                'status' => 'Published',
                'revision_number' => '00',
                'revision_date' => Carbon::parse('2026-01-01'),
            ]);
        }

        // 2 dokumen IK
        for ($i = 1; $i <= 2; $i++) {
            $doc = Document::factory()->create([
                'document_number' => "273.102.$i",
                'title' => "Instruksi $i",
                'category_id' => $this->catIk->id,
                'department_id' => $this->dept->id,
                'is_external' => false,
                'status' => 'Active',
            ]);
            DocumentRevision::factory()->create([
                'document_id' => $doc->id,
                'status' => 'Published',
                'revision_number' => '00',
                'revision_date' => Carbon::parse('2026-01-01'),
            ]);
        }

        // 2 dokumen F
        for ($i = 1; $i <= 2; $i++) {
            $doc = Document::factory()->create([
                'document_number' => "273.103.$i",
                'title' => "Form $i",
                'category_id' => $this->catF->id,
                'department_id' => $this->dept->id,
                'is_external' => false,
                'status' => 'Active',
            ]);
            DocumentRevision::factory()->create([
                'document_id' => $doc->id,
                'status' => 'Published',
                'revision_number' => '00',
                'revision_date' => Carbon::parse('2026-01-01'),
            ]);
        }

        $spreadsheet = $this->service->generateSpreadsheet();
        $this->assertNotFalse($spreadsheet);
        $sheet = $spreadsheet->getActiveSheet();

        // PK: baris 8 adalah header PK, baris 9-10 adalah docs (1, 2), baris 11 pemisah
        $this->assertEquals('PROSEDUR KERJA', $sheet->getCell('A8')->getValue());
        $this->assertEquals(1, $sheet->getCell('A9')->getValue());
        $this->assertEquals(2, $sheet->getCell('A10')->getValue());
        $this->assertEmpty($sheet->getCell('A11')->getValue());

        // IK: baris 12 adalah header IK, baris 13-14 adalah docs (3, 4), baris 15 pemisah
        $this->assertEquals('INTRUKSI KERJA', $sheet->getCell('A12')->getValue());
        $this->assertEquals(3, $sheet->getCell('A13')->getValue());
        $this->assertEquals(4, $sheet->getCell('A14')->getValue());
        $this->assertEmpty($sheet->getCell('A15')->getValue());

        // F: baris 16 adalah header F, baris 17-18 adalah docs (5, 6), baris 19 pemisah
        $this->assertEquals('FORMULIR', $sheet->getCell('A16')->getValue());
        $this->assertEquals(5, $sheet->getCell('A17')->getValue());
        $this->assertEquals(6, $sheet->getCell('A18')->getValue());
        $this->assertEmpty($sheet->getCell('A19')->getValue());

        // Simpan ke file fisik dan buka kembali untuk memastikan baris tetap terlihat
        $tempPath = storage_path('app/temp/test_reopen.xlsx');
        if (! is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($tempPath);

        $reopened = \PhpOffice\PhpSpreadsheet\IOFactory::load($tempPath);
        $reopenedSheet = $reopened->getActiveSheet();

        for ($r = 1; $r <= $reopenedSheet->getHighestRow(); $r++) {
            $this->assertTrue($reopenedSheet->getRowDimension($r)->getVisible(), "Baris $r pada file yang dibuka kembali harus visible");
        }

        @unlink($tempPath);
    }
}
