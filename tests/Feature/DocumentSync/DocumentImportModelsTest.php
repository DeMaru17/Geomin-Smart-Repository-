<?php

namespace Tests\Feature\DocumentSync;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentImportBatch;
use App\Models\DocumentImportItem;
use App\Models\DocumentRevision;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DocumentImportModelsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', ['--database' => 'mysql']);
    }

    public function test_default_is_selected_is_strictly_false(): void
    {
        $batch = DocumentImportBatch::create([
            'batch_number' => 'SYNC-TEST-' . uniqid(),
            'scanned_by' => 1,
            'source_directory' => 'document-import',
            'status' => 'Preview_Ready',
        ]);

        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => '273.101 Preparasi Rev 15.pdf',
            'source_relative_path' => '273.101 Preparasi Rev 15.pdf',
            'file_extension' => 'pdf',
            'file_size_bytes' => 102400,
            'file_hash' => hash('sha256', 'dummy-content'),
            'parsed_document_number' => '273.101',
            'parsed_revision_number' => '15',
            'decision' => 'READY_TO_ATTACH',
            // Perhatikan: is_selected sengaja tidak dioper agar mengambil default schema database
        ]);

        // Refresh dari database
        $freshItem = DocumentImportItem::find($item->id);

        $this->assertFalse($freshItem->is_selected, 'Default is_selected WAJIB bernilai false.');
        $this->assertIsBool($freshItem->is_selected, 'is_selected harus di-cast sebagai boolean.');
    }

    public function test_model_casts_and_relationships_work_correctly(): void
    {
        $category = DocumentCategory::firstOrCreate(
            ['code' => 'PK'],
            ['name' => 'Prosedur Kerja']
        );

        $document = Document::firstOrCreate(
            ['document_number' => '273.999'],
            [
                'title' => 'Dokumen Pengujian Staging',
                'category_id' => $category->id,
                'retention_period_months' => 36,
                'status' => 'Active',
            ]
        );

        $revision = DocumentRevision::firstOrCreate(
            [
                'document_id' => $document->id,
                'revision_number' => '01',
            ],
            [
                'file_path' => 'documents/273999_rev01.pdf',
                'status' => 'Published',
                'uploader_id' => 1,
            ]
        );

        $batch = DocumentImportBatch::create([
            'batch_number' => 'SYNC-REL-' . uniqid(),
            'scanned_by' => 1,
            'source_directory' => 'document-import',
            'status' => 'Scanning',
            'total_files_scanned' => 5,
            'confirmed_at' => now(),
        ]);

        $item = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => '273.999 Uji Rev 01.pdf',
            'source_relative_path' => 'sub/273.999 Uji Rev 01.pdf',
            'file_extension' => 'pdf',
            'file_size_bytes' => 204800,
            'file_hash' => hash('sha256', 'test-content'),
            'matched_document_id' => $document->id,
            'matched_revision_id' => $revision->id,
            'target_slot' => 'file_path',
            'decision' => 'PLACEHOLDER_REPLACEABLE',
            'is_selected' => true,
        ]);

        // Verifikasi relasi
        $this->assertCount(1, $batch->items);
        $this->assertSame($batch->id, $item->batch->id);
        $this->assertSame($document->id, $item->document->id);
        $this->assertSame($revision->id, $item->revision->id);

        // Verifikasi casts
        $this->assertIsInt($batch->total_files_scanned);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $batch->confirmed_at);
        $this->assertIsInt($item->file_size_bytes);
        $this->assertTrue($item->is_selected);

        // Verifikasi helper method
        $this->assertTrue($item->isEligibleForInstallation());

        $occupiedItem = new DocumentImportItem([
            'decision' => 'SLOT_OCCUPIED',
            'slot_current_status' => 'SLOT_OCCUPIED_LOCKED',
        ]);
        $this->assertFalse($occupiedItem->isEligibleForInstallation());
    }

    public function test_scopes_filter_expected_records(): void
    {
        $batch = DocumentImportBatch::create([
            'batch_number' => 'SYNC-SCOPE-' . uniqid(),
            'scanned_by' => 1,
            'source_directory' => 'document-import',
            'status' => 'Preview_Ready',
        ]);

        $item1 = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => 'file1.pdf',
            'source_relative_path' => 'file1.pdf',
            'file_extension' => 'pdf',
            'file_size_bytes' => 1000,
            'file_hash' => 'hash1',
            'decision' => 'READY_TO_ATTACH',
            'is_selected' => true,
        ]);

        $item2 = DocumentImportItem::create([
            'batch_id' => $batch->id,
            'source_filename' => 'file2.pdf',
            'source_relative_path' => 'file2.pdf',
            'file_extension' => 'pdf',
            'file_size_bytes' => 2000,
            'file_hash' => 'hash2',
            'decision' => 'CONFLICT_DUPLICATE_SOURCE',
            'is_selected' => false,
        ]);

        $eligible = DocumentImportItem::where('batch_id', $batch->id)->eligible()->pluck('id');
        $this->assertTrue($eligible->contains($item1->id));
        $this->assertFalse($eligible->contains($item2->id));

        $selected = DocumentImportItem::where('batch_id', $batch->id)->selected()->pluck('id');
        $this->assertTrue($selected->contains($item1->id));
        $this->assertFalse($selected->contains($item2->id));

        $manual = DocumentImportItem::where('batch_id', $batch->id)->manualReview()->pluck('id');
        $this->assertFalse($manual->contains($item1->id));
        $this->assertTrue($manual->contains($item2->id));
    }
}
