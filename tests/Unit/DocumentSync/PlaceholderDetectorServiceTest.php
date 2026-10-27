<?php

namespace Tests\Unit\DocumentSync;

use App\Models\DocumentRevision;
use App\Services\DocumentSync\PlaceholderDetectorService;
use Exception;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlaceholderDetectorServiceTest extends TestCase
{
    protected PlaceholderDetectorService $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new PlaceholderDetectorService();
        Storage::fake('local');
        config(['document_sync.disk' => 'local']);
    }

    public function test_detects_empty_or_whitespace_path(): void
    {
        $this->assertSame(
            PlaceholderDetectorService::STATUS_EMPTY,
            $this->detector->detectPath('')
        );

        $this->assertSame(
            PlaceholderDetectorService::STATUS_EMPTY,
            $this->detector->detectPath('   ')
        );

        $this->assertSame(
            PlaceholderDetectorService::STATUS_EMPTY,
            $this->detector->detectPath(null)
        );
    }

    public function test_detects_physical_file_exists(): void
    {
        Storage::disk('local')->put('documents/real_file.pdf', 'konten dokumen asli');

        $this->assertSame(
            PlaceholderDetectorService::STATUS_PHYSICAL_EXISTS,
            $this->detector->detectPath('documents/real_file.pdf')
        );

        // Sekalipun nama mirip seeder, jika fisik file benar-benar ada, prioritas adalah PHYSICAL_EXISTS
        Storage::disk('local')->put('documents/273101_rev15.pdf', 'file fisik ada');
        $this->assertSame(
            PlaceholderDetectorService::STATUS_PHYSICAL_EXISTS,
            $this->detector->detectPath('documents/273101_rev15.pdf')
        );
    }

    public function test_detects_internal_seeder_placeholder_when_physical_file_missing(): void
    {
        $path1 = 'documents/273101_rev15.pdf';
        $status1 = $this->detector->detectPath($path1);

        $this->assertSame(PlaceholderDetectorService::STATUS_PLACEHOLDER_INTERNAL, $status1);
        $this->assertTrue($this->detector->isRecognizedPlaceholder($status1));

        $path2 = 'documents/273101101_rev09.pdf';
        $status2 = $this->detector->detectPath($path2);

        $this->assertSame(PlaceholderDetectorService::STATUS_PLACEHOLDER_INTERNAL, $status2);
        $this->assertTrue($this->detector->isRecognizedPlaceholder($status2));
    }

    public function test_detects_external_seeder_placeholder_when_physical_file_missing(): void
    {
        $path = 'documents/external/e001.pdf';
        $status = $this->detector->detectPath($path);

        $this->assertSame(PlaceholderDetectorService::STATUS_PLACEHOLDER_EXTERNAL, $status);
        $this->assertTrue($this->detector->isRecognizedPlaceholder($status));
    }

    public function test_detects_broken_reference_for_unknown_path_without_physical_file(): void
    {
        // Path buatan manual yang tidak ada fisiknya dan bukan pola seeder resmi
        $path = 'manual_uploads/unknown_document.pdf';
        $status = $this->detector->detectPath($path);

        $this->assertSame(PlaceholderDetectorService::STATUS_BROKEN_REFERENCE, $status);
        $this->assertFalse($this->detector->isRecognizedPlaceholder($status));
    }

    public function test_detects_storage_unreachable_on_exception(): void
    {
        // Mock disk untuk melempar exception saat exists dipanggil
        $mockDisk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $mockDisk->shouldReceive('exists')
            ->andThrow(new Exception('Storage connection timed out'));

        Storage::set('broken_disk', $mockDisk);

        $status = $this->detector->detectPath('documents/sample.pdf', 'broken_disk');

        $this->assertSame(PlaceholderDetectorService::STATUS_STORAGE_UNREACHABLE, $status);
    }

    public function test_detect_for_revision_inspects_correct_slot(): void
    {
        $revision = new DocumentRevision();
        $revision->file_path = 'documents/273101_rev15.pdf';
        $revision->word_file_path = null;

        $pdfStatus = $this->detector->detectForRevision($revision, 'file_path');
        $this->assertSame(PlaceholderDetectorService::STATUS_PLACEHOLDER_INTERNAL, $pdfStatus);

        $wordStatus = $this->detector->detectForRevision($revision, 'word_file_path');
        $this->assertSame(PlaceholderDetectorService::STATUS_EMPTY, $wordStatus);
    }
}
