<?php

namespace Tests\Unit\DocumentSync;

use App\Services\DocumentSync\FilenameParserService;
use Tests\TestCase;

class FilenameParserServiceTest extends TestCase
{
    protected FilenameParserService $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FilenameParserService();
    }

    public function test_parses_standard_internal_and_external_documents(): void
    {
        // 3-segmen IK
        $info1 = $this->parser->parse('273.101.101 Penerimaan dan Pengecekan Sampel Rev.09.pdf');
        $this->assertSame('273.101.101', $info1->documentNumber);
        $this->assertSame('09', $info1->revisionNumber);
        $this->assertSame('pdf', $info1->extension);
        $this->assertTrue($info1->isParseComplete());
        $this->assertSame('PARSED', $info1->parseStatus);

        // 2-segmen PK
        $info2 = $this->parser->parse('273.101 Preparasi Rev 15.PDF');
        $this->assertSame('273.101', $info2->documentNumber);
        $this->assertSame('15', $info2->revisionNumber);
        $this->assertSame('pdf', $info2->extension, 'Ekstensi harus dinormalisasi ke huruf kecil.');
        $this->assertTrue($info2->isParseComplete());

        // Slug seeder internal
        $info3 = $this->parser->parse('273101105_rev09.pdf');
        $this->assertSame('273.101.105', $info3->documentNumber);
        $this->assertSame('09', $info3->revisionNumber);

        // Dokumen Eksternal
        $info4 = $this->parser->parse('E001 Methods for Chemical Analysis Rev 00.pdf');
        $this->assertSame('E001', $info4->documentNumber);
        $this->assertSame('00', $info4->revisionNumber);
    }

    public function test_normalizes_single_digit_revision_with_zero_padding(): void
    {
        $info1 = $this->parser->parse('273.101 Preparasi Rev 1.docx');
        $this->assertSame('01', $info1->revisionNumber);

        $info2 = $this->parser->parse('273.101 Preparasi Revisi 2.docx');
        $this->assertSame('02', $info2->revisionNumber);

        $info3 = $this->parser->parse('273.101 Preparasi Rev. 5.docx');
        $this->assertSame('05', $info3->revisionNumber);

        $info4 = $this->parser->parse('273.101 Preparasi Rev-06.docx');
        $this->assertSame('06', $info4->revisionNumber);
    }

    public function test_does_not_mistake_single_letter_r_or_year_or_code_as_revision(): void
    {
        // 'R1' (misal Ruang 1 atau Reagen 1) tidak boleh dianggap sebagai revisi jika tidak ada kata 'Rev'/'Revisi'
        $infoR = $this->parser->parse('273.101 Preparasi Ruang R1.docx');
        $this->assertSame('273.101', $infoR->documentNumber);
        $this->assertNull($infoR->revisionNumber);
        $this->assertSame('NO_REVISION', $infoR->parseStatus);

        // Tahun (misal 2024) atau kode form tidak boleh disalahartikan sebagai revisi
        $infoYear = $this->parser->parse('273.101 Form Analisis Tahun 2024.xlsx');
        $this->assertSame('273.101', $infoYear->documentNumber);
        $this->assertNull($infoYear->revisionNumber);
        $this->assertSame('NO_REVISION', $infoYear->parseStatus);

        // Dokumen dengan kode F-01 dan Rev 02 harus menghasilkan revisi 02, bukan 01
        $infoFormCode = $this->parser->parse('273.101.101 Form F-01 Rev 02.xlsx');
        $this->assertSame('273.101.101', $infoFormCode->documentNumber);
        $this->assertSame('02', $infoFormCode->revisionNumber);
    }

    public function test_avoids_confusing_similar_or_overlapping_document_numbers(): void
    {
        // 273.101.101 tidak boleh terpotong menjadi 273.101
        $info = $this->parser->parse('273.101.101_rev01.pdf');
        $this->assertSame('273.101.101', $info->documentNumber);
        $this->assertNotSame('273.101', $info->documentNumber);
    }

    public function test_detects_blanko_and_master_role_hints(): void
    {
        $infoBlanko = $this->parser->parse('273.101.201 Form Penerimaan Sampel Blanko Rev 02.xlsx');
        $this->assertSame('blanko', $infoBlanko->roleHint);

        $infoMaster = $this->parser->parse('273.101.201 Master Penerimaan Sampel Rev 02.docx');
        $this->assertSame('master', $infoMaster->roleHint);

        $infoNeutral = $this->parser->parse('273.101.201 Penerimaan Sampel Rev 02.xlsx');
        $this->assertNull($infoNeutral->roleHint);
    }

    public function test_handles_filename_without_revision(): void
    {
        $info = $this->parser->parse('273.101 Preparasi Sampel.pdf');
        $this->assertSame('273.101', $info->documentNumber);
        $this->assertNull($info->revisionNumber);
        $this->assertFalse($info->isParseComplete());
        $this->assertSame('NO_REVISION', $info->parseStatus);
    }

    public function test_handles_filename_without_document_number(): void
    {
        $info = $this->parser->parse('Dokumen Prosedur Kerja Rev 01.pdf');
        $this->assertNull($info->documentNumber);
        $this->assertNull($info->revisionNumber);
        $this->assertFalse($info->isParseComplete());
        $this->assertSame('NO_DOCUMENT_NUMBER', $info->parseStatus);
    }

    public function test_marks_ambiguous_when_multiple_distinct_documents_found(): void
    {
        $info = $this->parser->parse('273.101 dan 273.102 Revisi Bersama Rev 01.pdf');
        $this->assertNull($info->documentNumber);
        $this->assertSame('AMBIGUOUS_DOCUMENT', $info->parseStatus);
    }

    public function test_marks_ambiguous_when_multiple_distinct_revisions_found(): void
    {
        $info = $this->parser->parse('273.101 Preparasi Rev 01 menuju Rev 02.pdf');
        $this->assertSame('273.101', $info->documentNumber);
        $this->assertNull($info->revisionNumber);
        $this->assertSame('AMBIGUOUS_REVISION', $info->parseStatus);
    }

    public function test_does_not_confuse_document_digits_with_revision_number(): void
    {
        // 101 atau 202 di dalam nomor dokumen tidak boleh dianggap sebagai nomor revisi
        $info = $this->parser->parse('273.106.202 Daftar Induk Dokumen Rev 04.xlsx');
        $this->assertSame('273.106.202', $info->documentNumber);
        $this->assertSame('04', $info->revisionNumber);
        $this->assertNotSame('02', $info->revisionNumber);
    }
}
