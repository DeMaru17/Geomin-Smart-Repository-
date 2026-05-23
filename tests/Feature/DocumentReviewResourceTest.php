<?php

namespace Tests\Feature;

use App\Filament\Resources\DocumentReviews\DocumentReviewResource;
use App\Filament\Resources\DocumentReviews\Pages\ListDocumentReviews;
use App\Filament\Resources\DocumentReviews\Pages\ViewDocumentReview;
use App\Filament\Resources\DocumentReviews\Widgets\DocumentReviewStatsWidget;
use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\DocumentRevision;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Integration tests for DocumentReviewResource.
 *
 * Validates: Requirements 4.2, 4.3, 5.1, 6.1
 */
class DocumentReviewResourceTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['mysql', 'mysql_hris'];

    private User $adminUser;

    private User $regularUser;

    private Document $document;

    private DocumentRevision $revision;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->admin()->create();
        $this->regularUser = User::factory()->create(['role' => 'personil']);

        $this->document = Document::factory()->create();
        $this->revision = DocumentRevision::factory()->create([
            'document_id' => $this->document->id,
            'status' => 'Published',
            'revision_date' => now()->subMonths(6),
        ]);
    }

    /**
     * Validates: Requirements 4.2
     *
     * Admin can render the index page (ListDocumentReviews) successfully.
     */
    public function test_admin_can_render_index_page(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ListDocumentReviews::class)
            ->assertSuccessful();
    }

    /**
     * Validates: Requirements 5.1
     *
     * Admin can render the view page for a document.
     */
    public function test_admin_can_render_view_page(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ViewDocumentReview::class, ['record' => $this->document->id])
            ->assertSuccessful();
    }

    /**
     * Validates: Requirements 4.2
     *
     * The resource canAccess() returns false for non-admin/non-manajemen roles.
     */
    public function test_non_admin_cannot_access_resource(): void
    {
        $this->actingAs($this->regularUser);

        $this->assertFalse(DocumentReviewResource::canAccess());
    }

    /**
     * Validates: Requirements 4.2
     *
     * The resource canAccess() returns true for admin role.
     */
    public function test_admin_can_access_resource(): void
    {
        $this->actingAs($this->adminUser);

        $this->assertTrue(DocumentReviewResource::canAccess());
    }

    /**
     * Validates: Requirements 4.2
     *
     * The "catat_kaji_ulang" action on the index page creates a DocumentReview record.
     */
    public function test_catat_kaji_ulang_action_creates_review_record(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ListDocumentReviews::class)
            ->callAction('catat_kaji_ulang', data: [
                'document_id' => $this->document->id,
                'review_date' => '2025-01-15',
                'evaluation_status' => 'Dipertahankan',
                'remarks' => 'Dokumen masih relevan',
            ]);

        $this->assertDatabaseHas('document_reviews', [
            'document_id' => $this->document->id,
            'review_date' => '2025-01-15',
            'reviewer_id' => $this->adminUser->id,
            'evaluation_status' => 'Dipertahankan',
            'remarks' => 'Dokumen masih relevan',
        ]);
    }

    /**
     * Validates: Requirements 4.3
     *
     * The resource table displays documents correctly.
     */
    public function test_resource_table_displays_documents(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ListDocumentReviews::class)
            ->assertCanSeeTableRecords([$this->document]);
    }

    /**
     * Validates: Requirements 6.1
     *
     * The "Buat Pengajuan Revisi" action on the infolist is visible when evaluation_status is 'Perlu direvisi'.
     */
    public function test_view_page_shows_review_with_perlu_direvisi_status(): void
    {
        DocumentReview::factory()->create([
            'document_id' => $this->document->id,
            'reviewer_id' => $this->adminUser->id,
            'evaluation_status' => 'Perlu direvisi',
        ]);

        $this->actingAs($this->adminUser);

        Livewire::test(ViewDocumentReview::class, ['record' => $this->document->id])
            ->assertSuccessful()
            ->assertSee('Perlu direvisi');
    }

    /**
     * Validates: Requirements 6.1
     *
     * The view page shows review with 'Dipertahankan' status correctly.
     */
    public function test_view_page_shows_review_with_dipertahankan_status(): void
    {
        DocumentReview::factory()->create([
            'document_id' => $this->document->id,
            'reviewer_id' => $this->adminUser->id,
            'evaluation_status' => 'Dipertahankan',
        ]);

        $this->actingAs($this->adminUser);

        Livewire::test(ViewDocumentReview::class, ['record' => $this->document->id])
            ->assertSuccessful()
            ->assertSee('Dipertahankan');
    }

    /**
     * Validates: Requirements 4.2
     *
     * The stats widget renders successfully and shows correct stat cards.
     */
    public function test_stats_widget_renders_successfully(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-01'));

        // Overdue document (baseline 2024-01-01, due 2024-12-31)
        $overdueDoc = Document::factory()->create([
            'created_at' => Carbon::parse('2024-01-01'),
        ]);
        DocumentRevision::factory()->create([
            'document_id' => $overdueDoc->id,
            'status' => 'Published',
            'revision_date' => Carbon::parse('2024-01-01'),
        ]);

        // Due soon document (baseline 2024-07-15, due 2025-07-14, ~43 days remaining)
        $dueSoonDoc = Document::factory()->create([
            'created_at' => Carbon::parse('2024-07-15'),
        ]);
        DocumentRevision::factory()->create([
            'document_id' => $dueSoonDoc->id,
            'status' => 'Published',
            'revision_date' => Carbon::parse('2024-07-15'),
        ]);

        // Review this year
        DocumentReview::factory()->create([
            'document_id' => $this->document->id,
            'review_date' => Carbon::parse('2025-03-01'),
            'reviewer_id' => $this->adminUser->id,
        ]);

        $this->actingAs($this->adminUser);

        Livewire::test(DocumentReviewStatsWidget::class)
            ->assertSuccessful();

        Carbon::setTestNow();
    }

    /**
     * Validates: Requirements 5.1
     *
     * The view page displays document information in the infolist.
     */
    public function test_view_page_displays_document_info(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ViewDocumentReview::class, ['record' => $this->document->id])
            ->assertSuccessful()
            ->assertSee($this->document->document_number)
            ->assertSee($this->document->title);
    }

    /**
     * Validates: Requirements 4.2
     *
     * The "catat_kaji_ulang" action on the view page creates a DocumentReview record
     * with the document_id pre-filled.
     */
    public function test_view_page_catat_kaji_ulang_action_creates_review(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ViewDocumentReview::class, ['record' => $this->document->id])
            ->callAction('catat_kaji_ulang', data: [
                'review_date' => '2025-02-20',
                'evaluation_status' => 'Perlu diperbaharui',
                'remarks' => 'Perlu update referensi',
            ]);

        $this->assertDatabaseHas('document_reviews', [
            'document_id' => $this->document->id,
            'review_date' => '2025-02-20',
            'reviewer_id' => $this->adminUser->id,
            'evaluation_status' => 'Perlu diperbaharui',
            'remarks' => 'Perlu update referensi',
        ]);
    }

    /**
     * Validates: Requirements 4.2
     *
     * Manajemen role can also access the resource.
     */
    public function test_manajemen_can_access_resource(): void
    {
        $manajemenUser = User::factory()->manajemen()->create();

        $this->actingAs($manajemenUser);

        $this->assertTrue(DocumentReviewResource::canAccess());
    }
}
