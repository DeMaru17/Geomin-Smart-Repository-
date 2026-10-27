<?php

namespace Tests\Unit\DocumentSync;

use App\Services\DocumentSync\DTO\MatchResult;
use App\Services\DocumentSync\DTO\ParsedFileInfo;
use Tests\TestCase;

class DocumentSyncDTOAndConfigTest extends TestCase
{
    public function test_config_contains_expected_keys_and_defaults(): void
    {
        $config = config('document_sync');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('source_path', $config);
        $this->assertArrayHasKey('max_files_per_batch', $config);
        $this->assertArrayHasKey('max_file_size_mb', $config);
        $this->assertArrayHasKey('max_total_batch_size_mb', $config);
        $this->assertArrayHasKey('allowed_extensions', $config);
        $this->assertArrayHasKey('disk', $config);
        $this->assertArrayHasKey('pdf_directory', $config);
        $this->assertArrayHasKey('word_directory', $config);

        $this->assertSame(500, $config['max_files_per_batch']);
        $this->assertSame(100, $config['max_file_size_mb']);
        $this->assertSame(1024, $config['max_total_batch_size_mb']);
        $this->assertSame(['pdf', 'doc', 'docx', 'xls', 'xlsx'], $config['allowed_extensions']);
        $this->assertSame('local', $config['disk']);
        $this->assertSame('geomin-documents', $config['pdf_directory']);
        $this->assertSame('geomin-documents/word', $config['word_directory']);
    }

    public function test_parsed_file_info_dto_behavior(): void
    {
        $dtoComplete = new ParsedFileInfo(
            filename: '273.101 Preparasi Rev 15.pdf',
            relativePath: 'sub/273.101 Preparasi Rev 15.pdf',
            extension: 'pdf',
            sizeBytes: 1048576,
            hash: 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            documentNumber: '273.101',
            revisionNumber: '15',
            roleHint: null,
        );

        $this->assertTrue($dtoComplete->isParseComplete());
        $arrayData = $dtoComplete->toArray();
        $this->assertSame('273.101', $arrayData['document_number']);
        $this->assertSame('15', $arrayData['revision_number']);
        $this->assertTrue($arrayData['is_parse_complete']);

        $dtoIncomplete = new ParsedFileInfo(
            filename: '273.101 Preparasi.pdf',
            relativePath: '273.101 Preparasi.pdf',
            extension: 'pdf',
            sizeBytes: 500,
            hash: 'hash123',
            documentNumber: '273.101',
            revisionNumber: null,
        );

        $this->assertFalse($dtoIncomplete->isParseComplete());
    }

    public function test_match_result_dto_behavior(): void
    {
        $parsedInfo = new ParsedFileInfo(
            filename: '273.101 Preparasi Rev 15.pdf',
            relativePath: '273.101 Preparasi Rev 15.pdf',
            extension: 'pdf',
            sizeBytes: 1000,
            hash: 'hash123',
            documentNumber: '273.101',
            revisionNumber: '15',
        );

        $eligibleMatch = new MatchResult(
            parsedInfo: $parsedInfo,
            documentId: 1,
            revisionId: 1,
            documentNumber: '273.101',
            revisionNumber: '15',
            categoryCode: 'PK',
            targetSlot: 'file_path',
            matchStatus: 'MATCHED',
            slotCurrentStatus: 'SLOT_EMPTY',
            decision: 'READY_TO_ATTACH',
        );

        $this->assertTrue($eligibleMatch->isEligibleForSelection());

        $array = $eligibleMatch->toArray();
        $this->assertSame('READY_TO_ATTACH', $array['decision']);
        $this->assertFalse($array['is_selected'], 'Default is_selected di DTO toArray WAJIB false.');

        $ineligibleMatch = new MatchResult(
            parsedInfo: $parsedInfo,
            documentId: 1,
            revisionId: 1,
            decision: 'SLOT_OCCUPIED',
            slotCurrentStatus: 'SLOT_OCCUPIED_LOCKED',
        );

        $this->assertFalse($ineligibleMatch->isEligibleForSelection());
    }
}
