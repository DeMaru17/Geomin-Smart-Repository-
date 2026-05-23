<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\DocumentRevision;
use App\Services\DocumentReviewService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    private DocumentReviewService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DocumentReviewService();
    }

    public function test_baseline_date_uses_created_at_when_no_revisions_or_reviews(): void
    {
        $document = Document::factory()->create([
            'created_at' => Carbon::parse('2024-01-15'),
        ]);
        $document->load(['revisions', 'reviews']);

        $baseline = $this->service->getBaselineDate($document);

        $this->assertTrue($baseline->isSameDay(Carbon::parse('2024-01-15')));
    }

    public function test_baseline_date_uses_published_revision_date_when_no_reviews(): void
    {
        $document = Document::factory()->create([
            'created_at' => Carbon::parse('2023-01-01'),
        ]);

        DocumentRevision::factory()->create([
            'document_id' => $document->id,
            'status' => 'Published',
            'revision_date' => Carbon::parse('2024-06-15'),
        ]);

        $document->load(['revisions', 'reviews']);

        $baseline = $this->service->getBaselineDate($document);

        $this->assertTrue($baseline->isSameDay(Carbon::parse('2024-06-15')));
    }

    public function test_baseline_date_uses_review_date_when_no_published_revisions(): void
    {
        $document = Document::factory()->create([
            'created_at' => Carbon::parse('2023-01-01'),
        ]);

        // Draft revision only (not Published/Terbit)
        DocumentRevision::factory()->create([
            'document_id' => $document->id,
            'status' => 'Draft',
            'revision_date' => Carbon::parse('2024-08-01'),
        ]);

        DocumentReview::factory()->create([
            'document_id' => $document->id,
            'review_date' => Carbon::parse('2024-05-20'),
        ]);

        $document->load(['revisions', 'reviews']);

        $baseline = $this->service->getBaselineDate($document);

        $this->assertTrue($baseline->isSameDay(Carbon::parse('2024-05-20')));
    }

    public function test_baseline_date_uses_max_of_revision_and_review_dates(): void
    {
        $document = Document::factory()->create([
            'created_at' => Carbon::parse('2023-01-01'),
        ]);

        DocumentRevision::factory()->create([
            'document_id' => $document->id,
            'status' => 'Terbit',
            'revision_date' => Carbon::parse('2024-03-10'),
        ]);

        DocumentReview::factory()->create([
            'document_id' => $document->id,
            'review_date' => Carbon::parse('2024-07-25'),
        ]);

        $document->load(['revisions', 'reviews']);

        $baseline = $this->service->getBaselineDate($document);

        // Review date is more recent, so it should be the baseline
        $this->assertTrue($baseline->isSameDay(Carbon::parse('2024-07-25')));
    }

    public function test_baseline_date_uses_revision_when_more_recent_than_review(): void
    {
        $document = Document::factory()->create([
            'created_at' => Carbon::parse('2023-01-01'),
        ]);

        DocumentRevision::factory()->create([
            'document_id' => $document->id,
            'status' => 'Published',
            'revision_date' => Carbon::parse('2024-09-01'),
        ]);

        DocumentReview::factory()->create([
            'document_id' => $document->id,
            'review_date' => Carbon::parse('2024-03-15'),
        ]);

        $document->load(['revisions', 'reviews']);

        $baseline = $this->service->getBaselineDate($document);

        $this->assertTrue($baseline->isSameDay(Carbon::parse('2024-09-01')));
    }

    public function test_due_date_is_baseline_plus_365_days(): void
    {
        $document = Document::factory()->create([
            'created_at' => Carbon::parse('2024-01-01'),
        ]);
        $document->load(['revisions', 'reviews']);

        $dueDate = $this->service->getDueDate($document);

        $this->assertTrue($dueDate->isSameDay(Carbon::parse('2024-12-31')));
    }

    public function test_days_remaining_positive_when_not_due(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-01'));

        $document = Document::factory()->create([
            'created_at' => Carbon::parse('2025-01-01'),
        ]);
        $document->load(['revisions', 'reviews']);

        // Due date = 2025-01-01 + 365 = 2025-12-31 (or 2026-01-01)
        $daysRemaining = $this->service->getDaysRemaining($document);

        $this->assertEquals(365, $daysRemaining);

        Carbon::setTestNow();
    }

    public function test_days_remaining_negative_when_overdue(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-01'));

        $document = Document::factory()->create([
            'created_at' => Carbon::parse('2024-01-01'),
        ]);
        $document->load(['revisions', 'reviews']);

        // Baseline = 2024-01-01, Due = 2024-12-31
        // Today = 2025-06-01, so overdue by ~152 days
        $daysRemaining = $this->service->getDaysRemaining($document);

        $this->assertLessThan(0, $daysRemaining);

        Carbon::setTestNow();
    }

    public function test_get_upcoming_documents_includes_overdue_and_due_soon(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-01'));

        // Overdue document (baseline far in the past)
        $overdueDoc = Document::factory()->create([
            'created_at' => Carbon::parse('2024-01-01'),
        ]);
        DocumentRevision::factory()->create([
            'document_id' => $overdueDoc->id,
            'status' => 'Published',
            'revision_date' => Carbon::parse('2024-01-01'),
        ]);

        // Due soon document (due within 60 days)
        $dueSoonDoc = Document::factory()->create([
            'created_at' => Carbon::parse('2024-06-20'),
        ]);
        DocumentRevision::factory()->create([
            'document_id' => $dueSoonDoc->id,
            'status' => 'Terbit',
            'revision_date' => Carbon::parse('2024-06-20'),
        ]);

        // Not due yet (far future)
        $notDueDoc = Document::factory()->create([
            'created_at' => Carbon::parse('2025-05-01'),
        ]);
        DocumentRevision::factory()->create([
            'document_id' => $notDueDoc->id,
            'status' => 'Published',
            'revision_date' => Carbon::parse('2025-05-01'),
        ]);

        $upcoming = $this->service->getUpcomingDocuments();

        $this->assertTrue($upcoming->contains('id', $overdueDoc->id));
        $this->assertTrue($upcoming->contains('id', $dueSoonDoc->id));
        $this->assertFalse($upcoming->contains('id', $notDueDoc->id));

        Carbon::setTestNow();
    }

    public function test_get_overdue_documents_only_includes_past_due(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-01'));

        $overdueDoc = Document::factory()->create([
            'created_at' => Carbon::parse('2024-01-01'),
        ]);
        DocumentRevision::factory()->create([
            'document_id' => $overdueDoc->id,
            'status' => 'Published',
            'revision_date' => Carbon::parse('2024-01-01'),
        ]);

        $notDueDoc = Document::factory()->create([
            'created_at' => Carbon::parse('2025-05-01'),
        ]);
        DocumentRevision::factory()->create([
            'document_id' => $notDueDoc->id,
            'status' => 'Published',
            'revision_date' => Carbon::parse('2025-05-01'),
        ]);

        $overdue = $this->service->getOverdueDocuments();

        $this->assertTrue($overdue->contains('id', $overdueDoc->id));
        $this->assertFalse($overdue->contains('id', $notDueDoc->id));

        Carbon::setTestNow();
    }

    public function test_get_due_soon_documents_excludes_overdue(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-01'));

        // Overdue document
        $overdueDoc = Document::factory()->create([
            'created_at' => Carbon::parse('2024-01-01'),
        ]);
        DocumentRevision::factory()->create([
            'document_id' => $overdueDoc->id,
            'status' => 'Published',
            'revision_date' => Carbon::parse('2024-01-01'),
        ]);

        // Due soon (within 60 days but not overdue)
        $dueSoonDoc = Document::factory()->create([
            'created_at' => Carbon::parse('2024-06-20'),
        ]);
        DocumentRevision::factory()->create([
            'document_id' => $dueSoonDoc->id,
            'status' => 'Terbit',
            'revision_date' => Carbon::parse('2024-06-20'),
        ]);

        $dueSoon = $this->service->getDueSoonDocuments();

        $this->assertFalse($dueSoon->contains('id', $overdueDoc->id));
        $this->assertTrue($dueSoon->contains('id', $dueSoonDoc->id));

        Carbon::setTestNow();
    }

    public function test_only_active_documents_with_published_revisions_are_included(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-01'));

        // Document with only Draft revision (not active)
        $draftDoc = Document::factory()->create([
            'created_at' => Carbon::parse('2024-01-01'),
        ]);
        DocumentRevision::factory()->create([
            'document_id' => $draftDoc->id,
            'status' => 'Draft',
            'revision_date' => Carbon::parse('2024-01-01'),
        ]);

        // Document with no revisions (not active)
        $noRevDoc = Document::factory()->create([
            'created_at' => Carbon::parse('2024-01-01'),
        ]);

        $upcoming = $this->service->getUpcomingDocuments();
        $overdue = $this->service->getOverdueDocuments();

        $this->assertFalse($upcoming->contains('id', $draftDoc->id));
        $this->assertFalse($upcoming->contains('id', $noRevDoc->id));
        $this->assertFalse($overdue->contains('id', $draftDoc->id));
        $this->assertFalse($overdue->contains('id', $noRevDoc->id));

        Carbon::setTestNow();
    }

    public function test_baseline_ignores_obsolete_revisions(): void
    {
        $document = Document::factory()->create([
            'created_at' => Carbon::parse('2023-01-01'),
        ]);

        // Obsolete revision with newer date should be ignored
        DocumentRevision::factory()->create([
            'document_id' => $document->id,
            'status' => 'Obsolete',
            'revision_date' => Carbon::parse('2024-12-01'),
        ]);

        // Published revision with older date
        DocumentRevision::factory()->create([
            'document_id' => $document->id,
            'status' => 'Published',
            'revision_date' => Carbon::parse('2024-06-01'),
        ]);

        $document->load(['revisions', 'reviews']);

        $baseline = $this->service->getBaselineDate($document);

        $this->assertTrue($baseline->isSameDay(Carbon::parse('2024-06-01')));
    }
}
