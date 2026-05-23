<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\DocumentReviewNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Property-based test for cascade delete behavior.
 *
 * Verifies that deleting a Document removes all associated
 * DocumentReview and DocumentReviewNotification records via
 * foreign key cascade delete.
 */
class CascadeDeletePropertyTest extends TestCase
{
    use RefreshDatabase;

    protected int $iterations = 100;

    /**
     * Validates: Requirements 1.2
     *
     * Property 16: Cascade delete removes all related reviews
     *
     * For any document with N associated DocumentReview records (where N >= 0),
     * deleting the document SHALL result in exactly 0 DocumentReview records
     * remaining for that document_id. The same applies to DocumentReviewNotification records.
     */
    #[Group('Feature: document-review-module, Property 16: Cascade delete removes all related reviews')]
    public function test_cascade_delete_removes_all_related_reviews_and_notifications(): void
    {
        $faker = \Faker\Factory::create('id_ID');

        for ($iteration = 0; $iteration < $this->iterations; $iteration++) {
            // Create a document
            $document = Document::factory()->create();
            $documentId = $document->id;

            // Create a random number of DocumentReview records (0 to 5)
            $reviewCount = $faker->numberBetween(0, 5);
            for ($r = 0; $r < $reviewCount; $r++) {
                DocumentReview::factory()->create([
                    'document_id' => $documentId,
                    'review_date' => $faker->date(),
                ]);
            }

            // Create a random number of DocumentReviewNotification records (0 to 3)
            $notificationCount = $faker->numberBetween(0, 3);
            for ($n = 0; $n < $notificationCount; $n++) {
                DocumentReviewNotification::create([
                    'document_id' => $documentId,
                    'due_date' => Carbon::parse($faker->unique()->dateTimeBetween('-1 year', '+1 year'))->toDateString(),
                    'panel_notified_at' => $faker->optional(50)->dateTime(),
                    'email_notified_at' => $faker->optional(30)->dateTime(),
                    'overdue_notified_at' => $faker->optional(20)->dateTime(),
                ]);
            }
            $faker->unique(true); // Reset unique generator for next iteration

            // Verify records exist before deletion
            $this->assertEquals(
                $reviewCount,
                DocumentReview::where('document_id', $documentId)->count(),
                "Iteration {$iteration}: Expected {$reviewCount} reviews before deletion"
            );
            $this->assertEquals(
                $notificationCount,
                DocumentReviewNotification::where('document_id', $documentId)->count(),
                "Iteration {$iteration}: Expected {$notificationCount} notifications before deletion"
            );

            // Also create another document with its own reviews to ensure
            // cascade only affects the deleted document's records
            $otherDocument = Document::factory()->create();
            $otherReviewCount = $faker->numberBetween(1, 3);
            for ($r = 0; $r < $otherReviewCount; $r++) {
                DocumentReview::factory()->create([
                    'document_id' => $otherDocument->id,
                    'review_date' => $faker->date(),
                ]);
            }

            // Delete the first document
            $document->delete();

            // Property assertion: All associated DocumentReview records are gone
            $remainingReviews = DocumentReview::where('document_id', $documentId)->count();
            $this->assertEquals(
                0,
                $remainingReviews,
                "Iteration {$iteration}: Expected 0 reviews after cascade delete, found {$remainingReviews}. "
                    . "Original count was {$reviewCount}."
            );

            // Property assertion: All associated DocumentReviewNotification records are gone
            $remainingNotifications = DocumentReviewNotification::where('document_id', $documentId)->count();
            $this->assertEquals(
                0,
                $remainingNotifications,
                "Iteration {$iteration}: Expected 0 notifications after cascade delete, found {$remainingNotifications}. "
                    . "Original count was {$notificationCount}."
            );

            // Property assertion: Other document's reviews are NOT affected
            $otherRemainingReviews = DocumentReview::where('document_id', $otherDocument->id)->count();
            $this->assertEquals(
                $otherReviewCount,
                $otherRemainingReviews,
                "Iteration {$iteration}: Other document's reviews should not be affected by cascade delete. "
                    . "Expected {$otherReviewCount}, found {$otherRemainingReviews}."
            );

            // Clean up for next iteration
            $otherDocument->delete();
        }
    }
}
