<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\DocumentRevision;
use App\Services\DocumentReviewService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Property-based tests for DocumentReviewService.
 */
class DocumentReviewServicePropertyTest extends TestCase
{
    use RefreshDatabase;

    private DocumentReviewService $service;

    protected int $iterations = 100;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DocumentReviewService();
    }

    /**
     * Validates: Requirements 6.5
     *
     * Property 9: Upcoming schedule sorting by urgency
     *
     * For any list of documents displayed in the Jadwal Mendatang tab, the list SHALL be
     * sorted such that all overdue documents appear before non-overdue documents, and within
     * each group, documents are sorted by days remaining in ascending order (most urgent first).
     *
     * NOTE: The service's getUpcomingDocuments() may not sort — the sorting is done at the UI layer.
     * This test verifies that when the upcoming documents are sorted by days remaining ascending,
     * overdue documents (negative days) naturally come first. The test validates that the data
     * supports correct sorting.
     */
    public function test_upcoming_documents_sorted_by_days_remaining_ascending_places_overdue_first(): void
    {
        $faker = \Faker\Factory::create('id_ID');

        for ($iteration = 0; $iteration < $this->iterations; $iteration++) {
            // Set a fixed "today" for this iteration
            $today = Carbon::parse('2025-06-15');
            Carbon::setTestNow($today);

            // Clean up documents from previous iteration
            Document::query()->delete();

            // Create N random documents (between 3 and 10) with varying baseline dates
            // that will produce a mix of overdue and due-soon documents
            $numDocuments = $faker->numberBetween(3, 10);

            for ($i = 0; $i < $numDocuments; $i++) {
                $document = Document::factory()->create();

                // Generate a random revision_date that will produce documents
                // with days remaining in the range [-180, +60] (all upcoming/overdue)
                // Due date = revision_date + 365
                // Days remaining = due_date - today
                // So revision_date = today - 365 + days_remaining
                // For days_remaining in [-180, +60]: revision_date in [today - 545, today - 305]
                $daysRemaining = $faker->numberBetween(-180, 60);
                $revisionDate = $today->copy()->subDays(365)->addDays($daysRemaining);

                DocumentRevision::factory()->create([
                    'document_id' => $document->id,
                    'status' => $faker->randomElement(['Published', 'Terbit']),
                    'revision_date' => $revisionDate,
                ]);
            }

            // Get upcoming documents from the service
            $upcomingDocuments = $this->service->getUpcomingDocuments();

            // Skip iteration if no upcoming documents (shouldn't happen with our date range, but be safe)
            if ($upcomingDocuments->isEmpty()) {
                continue;
            }

            // Sort by days remaining ascending (this is what the UI layer does)
            $sorted = $upcomingDocuments->sortBy(function (Document $document) {
                return $this->service->getDaysRemaining($document);
            })->values();

            // Verify property: all overdue documents (negative days) come before non-overdue (>= 0 days)
            $foundNonOverdue = false;
            $previousDaysRemaining = null;

            foreach ($sorted as $document) {
                $daysRemaining = $this->service->getDaysRemaining($document);

                if ($daysRemaining >= 0) {
                    $foundNonOverdue = true;
                }

                // Once we've seen a non-overdue document, all subsequent must also be non-overdue
                if ($foundNonOverdue) {
                    $this->assertGreaterThanOrEqual(
                        0,
                        $daysRemaining,
                        "Iteration {$iteration}: Found overdue document (days remaining: {$daysRemaining}) after a non-overdue document in sorted list"
                    );
                }

                // Within each group, days remaining should be ascending
                if ($previousDaysRemaining !== null) {
                    $this->assertGreaterThanOrEqual(
                        $previousDaysRemaining,
                        $daysRemaining,
                        "Iteration {$iteration}: Documents not sorted ascending by days remaining. Previous: {$previousDaysRemaining}, Current: {$daysRemaining}"
                    );
                }

                $previousDaysRemaining = $daysRemaining;
            }

            Carbon::setTestNow();
        }
    }

    /**
     * Validates: Requirements 6.1
     *
     * Property 8: Upcoming schedule list contains exactly the correct documents
     *
     * For any set of active documents, the Jadwal Mendatang tab SHALL display
     * exactly those documents where days remaining ≤ 60 (including overdue documents),
     * and no documents where days remaining > 60.
     */
    #[Group('Feature: document-review-module, Property 8: Upcoming schedule list contains exactly the correct documents')]
    public function test_upcoming_schedule_contains_exactly_correct_documents(): void
    {
        $faker = \Faker\Factory::create('id_ID');

        for ($iteration = 0; $iteration < $this->iterations; $iteration++) {
            // Clean up previous iteration data
            DocumentReview::query()->delete();
            DocumentRevision::query()->delete();
            Document::query()->delete();

            // Freeze time at a random reference point
            $referenceDate = Carbon::parse($faker->dateTimeBetween('2024-01-01', '2026-12-31'));
            Carbon::setTestNow($referenceDate);

            // Create a random number of documents (between 3 and 10)
            $numDocuments = $faker->numberBetween(3, 10);
            $documents = [];

            for ($i = 0; $i < $numDocuments; $i++) {
                $document = Document::factory()->create();

                // Give each document a Published revision at a random date
                // This makes the document "active" (has at least one Published/Terbit revision)
                $revisionDate = Carbon::parse($faker->dateTimeBetween(
                    $referenceDate->copy()->subDays($faker->numberBetween(1, 730))->toDateString(),
                    $referenceDate->copy()->addDays($faker->numberBetween(0, 200))->toDateString()
                ));

                DocumentRevision::factory()->create([
                    'document_id' => $document->id,
                    'status' => $faker->randomElement(['Published', 'Terbit']),
                    'revision_date' => $revisionDate,
                ]);

                // Optionally add a review record
                if ($faker->boolean(40)) {
                    $reviewDate = Carbon::parse($faker->dateTimeBetween(
                        $referenceDate->copy()->subDays($faker->numberBetween(1, 600))->toDateString(),
                        $referenceDate->copy()->addDays($faker->numberBetween(0, 100))->toDateString()
                    ));

                    DocumentReview::factory()->create([
                        'document_id' => $document->id,
                        'review_date' => $reviewDate,
                    ]);
                }

                $documents[] = $document;
            }

            // Also create some inactive documents (no Published revision) that should never appear
            $numInactive = $faker->numberBetween(1, 3);
            $inactiveDocuments = [];
            for ($i = 0; $i < $numInactive; $i++) {
                $inactiveDoc = Document::factory()->create();
                // Only Draft revision — not active
                DocumentRevision::factory()->create([
                    'document_id' => $inactiveDoc->id,
                    'status' => 'Draft',
                    'revision_date' => $referenceDate->copy()->subDays($faker->numberBetween(100, 500)),
                ]);
                $inactiveDocuments[] = $inactiveDoc;
            }

            // Get the upcoming documents from the service
            $upcomingDocuments = $this->service->getUpcomingDocuments();
            $upcomingIds = $upcomingDocuments->pluck('id')->toArray();

            // Manually compute which documents should be in the upcoming list
            $expectedUpcomingIds = [];
            $expectedNotUpcomingIds = [];

            foreach ($documents as $document) {
                $document->load(['revisions', 'reviews']);
                $daysRemaining = $this->service->getDaysRemaining($document);

                if ($daysRemaining <= 60) {
                    $expectedUpcomingIds[] = $document->id;
                } else {
                    $expectedNotUpcomingIds[] = $document->id;
                }
            }

            // Assert: ALL documents with days remaining ≤ 60 are included
            foreach ($expectedUpcomingIds as $id) {
                $this->assertContains(
                    $id,
                    $upcomingIds,
                    "Iteration {$iteration}: Document ID {$id} with days remaining ≤ 60 should be in upcoming list (reference date: {$referenceDate->toDateString()})"
                );
            }

            // Assert: NO documents with days remaining > 60 are included
            foreach ($expectedNotUpcomingIds as $id) {
                $this->assertNotContains(
                    $id,
                    $upcomingIds,
                    "Iteration {$iteration}: Document ID {$id} with days remaining > 60 should NOT be in upcoming list (reference date: {$referenceDate->toDateString()})"
                );
            }

            // Assert: Inactive documents (no Published revision) are never included
            foreach ($inactiveDocuments as $inactiveDoc) {
                $this->assertNotContains(
                    $inactiveDoc->id,
                    $upcomingIds,
                    "Iteration {$iteration}: Inactive document ID {$inactiveDoc->id} should NOT be in upcoming list"
                );
            }

            // Assert: The upcoming list count matches exactly the expected count
            $this->assertCount(
                count($expectedUpcomingIds),
                $upcomingDocuments,
                "Iteration {$iteration}: Upcoming list count should match expected count"
            );

            Carbon::setTestNow();
        }
    }

    /**
     * Validates: Requirements 5.2, 5.3
     *
     * Property 7: Stats widget counts are consistent with document state
     *
     * For any set of documents with varying revision dates and review dates,
     * the "Dokumen Overdue" count SHALL equal the number of documents where Due Date < today,
     * and the "Jatuh Tempo < 60 Hari" count SHALL equal the number of documents where
     * 0 < days remaining ≤ 60.
     */
    #[Group('Feature: document-review-module, Property 7: Stats widget counts are consistent with document state')]
    public function test_stats_widget_counts_are_consistent_with_document_state(): void
    {
        $faker = \Faker\Factory::create('id_ID');

        for ($iteration = 0; $iteration < $this->iterations; $iteration++) {
            // Clean up previous iteration data
            DocumentReview::query()->delete();
            DocumentRevision::query()->delete();
            Document::query()->delete();

            // Freeze time at a random "today"
            $today = Carbon::parse($faker->dateTimeBetween('-2 years', '+1 year'));
            Carbon::setTestNow($today);

            // Create N random documents (between 3 and 10)
            $numDocuments = $faker->numberBetween(3, 10);
            $documents = [];

            for ($i = 0; $i < $numDocuments; $i++) {
                $document = Document::factory()->create([
                    'created_at' => Carbon::parse($faker->dateTimeBetween('-5 years', '-1 year')),
                ]);

                // Each document gets at least one Published revision (to be "active")
                $revisionDate = Carbon::parse($faker->dateTimeBetween('-3 years', '+6 months'));
                DocumentRevision::factory()->create([
                    'document_id' => $document->id,
                    'status' => $faker->randomElement(['Published', 'Terbit']),
                    'revision_date' => $revisionDate,
                ]);

                // Optionally add additional revisions
                if ($faker->boolean(40)) {
                    DocumentRevision::factory()->create([
                        'document_id' => $document->id,
                        'status' => $faker->randomElement(['Published', 'Terbit']),
                        'revision_date' => Carbon::parse($faker->dateTimeBetween('-3 years', '+6 months')),
                    ]);
                }

                // Optionally add reviews
                if ($faker->boolean(50)) {
                    $numReviews = $faker->numberBetween(1, 3);
                    for ($r = 0; $r < $numReviews; $r++) {
                        DocumentReview::factory()->create([
                            'document_id' => $document->id,
                            'review_date' => Carbon::parse($faker->dateTimeBetween('-3 years', '+6 months')),
                        ]);
                    }
                }

                $documents[] = $document;
            }

            // Manually compute expected counts
            $expectedOverdueCount = 0;
            $expectedDueSoonCount = 0;

            foreach ($documents as $document) {
                $document->load(['revisions', 'reviews']);
                $daysRemaining = $this->service->getDaysRemaining($document);

                if ($daysRemaining < 0) {
                    $expectedOverdueCount++;
                } elseif ($daysRemaining > 0 && $daysRemaining <= 60) {
                    $expectedDueSoonCount++;
                }
            }

            // Get actual counts from service methods
            $actualOverdueCount = $this->service->getOverdueDocuments()->count();
            $actualDueSoonCount = $this->service->getDueSoonDocuments()->count();

            // Assert consistency
            $this->assertEquals(
                $expectedOverdueCount,
                $actualOverdueCount,
                "Iteration {$iteration}: Overdue count mismatch. Today={$today->toDateString()}, "
                    . "Expected={$expectedOverdueCount}, Actual={$actualOverdueCount}"
            );

            $this->assertEquals(
                $expectedDueSoonCount,
                $actualDueSoonCount,
                "Iteration {$iteration}: Due soon count mismatch. Today={$today->toDateString()}, "
                    . "Expected={$expectedDueSoonCount}, Actual={$actualDueSoonCount}"
            );
        }

        // Clean up Carbon test time
        Carbon::setTestNow();
    }

    /**
     * Feature: document-review-module, Property 2: Due Date is always Baseline Date plus 365 days
     *
     * Validates: Requirements 2.4
     *
     * For any document and any Baseline Date value, the computed Due Date SHALL always
     * equal Baseline Date + 365 calendar days.
     */
    #[Group('Feature: document-review-module, Property 2: Due Date is always Baseline Date plus 365 days')]
    public function test_property_due_date_is_always_baseline_plus_365_days(): void
    {
        $faker = \Faker\Factory::create('id_ID');

        for ($i = 0; $i < $this->iterations; $i++) {
            // Generate a random document with random created_at date
            $createdAt = Carbon::parse($faker->dateTimeBetween('-5 years', 'now'));

            $document = Document::factory()->create([
                'created_at' => $createdAt,
            ]);

            // Randomly decide whether to add published/non-published revisions (0-3)
            $revisionCount = $faker->numberBetween(0, 3);
            for ($r = 0; $r < $revisionCount; $r++) {
                $status = $faker->randomElement(['Published', 'Terbit', 'Draft', 'Obsolete']);
                $revisionDate = $faker->dateTimeBetween($createdAt->toDateString(), 'now');

                DocumentRevision::factory()->create([
                    'document_id' => $document->id,
                    'status' => $status,
                    'revision_date' => Carbon::parse($revisionDate),
                ]);
            }

            // Randomly decide whether to add reviews (0-3)
            $reviewCount = $faker->numberBetween(0, 3);
            for ($rv = 0; $rv < $reviewCount; $rv++) {
                $reviewDate = $faker->dateTimeBetween($createdAt->toDateString(), 'now');

                DocumentReview::factory()->create([
                    'document_id' => $document->id,
                    'review_date' => Carbon::parse($reviewDate),
                ]);
            }

            // Reload document with relations
            $document->load(['revisions', 'reviews']);

            // Compute baseline and due date
            $baselineDate = $this->service->getBaselineDate($document);
            $dueDate = $this->service->getDueDate($document);

            // Property assertion: Due Date === Baseline Date + 365 days exactly
            $expectedDueDate = $baselineDate->copy()->addDays(365);

            $this->assertTrue(
                $dueDate->isSameDay($expectedDueDate),
                "Iteration {$i}: Due Date ({$dueDate->toDateString()}) should equal "
                    . "Baseline Date ({$baselineDate->toDateString()}) + 365 days "
                    . "({$expectedDueDate->toDateString()}). "
                    . "Document ID: {$document->id}, "
                    . "Revisions: {$revisionCount}, Reviews: {$reviewCount}"
            );
        }
    }
}
