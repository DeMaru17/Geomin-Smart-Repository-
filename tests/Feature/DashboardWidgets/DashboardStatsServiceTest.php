<?php

namespace Tests\Feature\DashboardWidgets;

use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\User;
use App\Services\DashboardStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    private DashboardStatsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DashboardStatsService();
    }

    public function test_get_active_document_count_returns_zero_when_no_documents(): void
    {
        $this->assertEquals(0, $this->service->getActiveDocumentCount());
    }

    public function test_get_active_document_count_only_counts_documents_with_published_revisions(): void
    {
        // Document with Published revision (active)
        $activeDoc = Document::factory()->create();
        DocumentRevision::factory()->create([
            'document_id' => $activeDoc->id,
            'status' => 'Published',
        ]);

        // Document with Terbit revision (active)
        $terbitDoc = Document::factory()->create();
        DocumentRevision::factory()->create([
            'document_id' => $terbitDoc->id,
            'status' => 'Terbit',
        ]);

        // Document with only Draft revision (not active)
        $draftDoc = Document::factory()->create();
        DocumentRevision::factory()->create([
            'document_id' => $draftDoc->id,
            'status' => 'Draft',
        ]);

        // Document with no revisions (not active)
        Document::factory()->create();

        $this->assertEquals(2, $this->service->getActiveDocumentCount());
    }

    public function test_get_internal_document_count_only_counts_internal_active_documents(): void
    {
        // Internal active document
        $internalDoc = Document::factory()->create(['is_external' => false]);
        DocumentRevision::factory()->create([
            'document_id' => $internalDoc->id,
            'status' => 'Published',
        ]);

        // External active document
        $externalDoc = Document::factory()->create(['is_external' => true]);
        DocumentRevision::factory()->create([
            'document_id' => $externalDoc->id,
            'status' => 'Published',
        ]);

        // Internal document without published revision (not active)
        $inactiveDraftDoc = Document::factory()->create(['is_external' => false]);
        DocumentRevision::factory()->create([
            'document_id' => $inactiveDraftDoc->id,
            'status' => 'Draft',
        ]);

        $this->assertEquals(1, $this->service->getInternalDocumentCount());
    }

    public function test_get_external_document_count_only_counts_external_active_documents(): void
    {
        // External active document
        $externalDoc = Document::factory()->create(['is_external' => true]);
        DocumentRevision::factory()->create([
            'document_id' => $externalDoc->id,
            'status' => 'Terbit',
        ]);

        // Internal active document
        $internalDoc = Document::factory()->create(['is_external' => false]);
        DocumentRevision::factory()->create([
            'document_id' => $internalDoc->id,
            'status' => 'Published',
        ]);

        $this->assertEquals(1, $this->service->getExternalDocumentCount());
    }

    public function test_get_published_revision_count_counts_all_published_and_terbit_revisions(): void
    {
        // Use separate documents to avoid the model event that marks
        // other Published/Terbit revisions as Obsolete on the same document
        $doc1 = Document::factory()->create();
        DocumentRevision::factory()->create([
            'document_id' => $doc1->id,
            'status' => 'Published',
        ]);

        $doc2 = Document::factory()->create();
        DocumentRevision::factory()->create([
            'document_id' => $doc2->id,
            'status' => 'Terbit',
        ]);

        $doc3 = Document::factory()->create();
        DocumentRevision::factory()->create([
            'document_id' => $doc3->id,
            'status' => 'Draft',
        ]);

        $doc4 = Document::factory()->create();
        DocumentRevision::factory()->create([
            'document_id' => $doc4->id,
            'status' => 'Obsolete',
        ]);

        $this->assertEquals(2, $this->service->getPublishedRevisionCount());
    }

    public function test_is_admin_or_manajemen_returns_true_for_admin(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->assertTrue($this->service->isAdminOrManajemen($user));
    }

    public function test_is_admin_or_manajemen_returns_true_for_manajemen(): void
    {
        $user = User::factory()->create(['role' => 'manajemen']);

        $this->assertTrue($this->service->isAdminOrManajemen($user));
    }

    public function test_is_admin_or_manajemen_returns_false_for_regular_user(): void
    {
        $user = User::factory()->create(['role' => 'personil']);

        $this->assertFalse($this->service->isAdminOrManajemen($user));
    }

    public function test_is_admin_or_manajemen_returns_false_when_no_user(): void
    {
        $this->assertFalse($this->service->isAdminOrManajemen(null));
    }

    public function test_is_admin_or_manajemen_uses_authenticated_user_when_null_passed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $this->assertTrue($this->service->isAdminOrManajemen());
    }

    public function test_internal_plus_external_equals_total_active(): void
    {
        // Create a mix of internal and external active documents
        for ($i = 0; $i < 3; $i++) {
            $doc = Document::factory()->create(['is_external' => false]);
            DocumentRevision::factory()->create([
                'document_id' => $doc->id,
                'status' => 'Published',
            ]);
        }

        for ($i = 0; $i < 2; $i++) {
            $doc = Document::factory()->create(['is_external' => true]);
            DocumentRevision::factory()->create([
                'document_id' => $doc->id,
                'status' => 'Terbit',
            ]);
        }

        $total = $this->service->getActiveDocumentCount();
        $internal = $this->service->getInternalDocumentCount();
        $external = $this->service->getExternalDocumentCount();

        $this->assertEquals($total, $internal + $external);
        $this->assertEquals(5, $total);
        $this->assertEquals(3, $internal);
        $this->assertEquals(2, $external);
    }
}
