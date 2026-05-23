<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\DocumentReviewNotification;
use App\Models\DocumentRevision;
use App\Models\User;
use App\Services\DocumentReviewService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Property-based tests for notification threshold and idempotency.
 */
class NotificationThresholdPropertyTest extends TestCase
{
    use RefreshDatabase;

    protected int $iterations = 100;

    /**
     * Validates: Requirements 3.7, 3.8
     *
     * Property 4: Notification sending is idempotent per period
     *
     * For any document and a given Due Date, running the check-document-reviews command
     * multiple times SHALL result in at most one panel notification, at most one email
     * notification, and at most one overdue notification being recorded for that
     * document+due_date combination.
     */
    #[Group('Feature: document-review-module, Property 4: Notification sending is idempotent per period')]
    public function test_notification_sending_is_idempotent_per_period(): void
    {
        $faker = \Faker\Factory::create('id_ID');

        for ($iteration = 0; $iteration < $this->iterations; $iteration++) {
            // Clean up previous iteration data
            DocumentReviewNotification::query()->delete();
            DocumentReview::query()->delete();
            DocumentRevision::query()->delete();
            Document::query()->delete();

            // Freeze time at a random reference date
            $today = Carbon::parse($faker->dateTimeBetween('2024-01-01', '2026-06-30'));
            Carbon::setTestNow($today);

            // Create a document with a Published revision
            $document = Document::factory()->create();

            // Determine a random notification scenario by choosing days remaining
            // We pick from ranges that trigger each notification type:
            // - overdue: days remaining <= 0 (revision_date such that due_date < today)
            // - email (30 days): 1 <= days remaining <= 30
            // - panel (60 days): 31 <= days remaining <= 60
            $scenario = $faker->randomElement(['overdue', 'email', 'panel']);
            $daysRemaining = match ($scenario) {
                'overdue' => $faker->numberBetween(-180, 0),
                'email' => $faker->numberBetween(1, 30),
                'panel' => $faker->numberBetween(31, 60),
            };

            // Calculate revision_date so that due_date = revision_date + 365
            // and days_remaining = due_date - today
            // => revision_date = today - 365 + days_remaining
            $revisionDate = $today->copy()->subDays(365)->addDays($daysRemaining);

            DocumentRevision::factory()->create([
                'document_id' => $document->id,
                'status' => $faker->randomElement(['Published', 'Terbit']),
                'revision_date' => $revisionDate,
            ]);

            // Ensure at least one admin/manajemen user exists for notification recipients
            $adminUser = User::where('role', 'admin')->first();
            if (! $adminUser) {
                $adminUser = User::factory()->create(['role' => 'admin']);
            }

            // Use Notification::fake() to intercept notifications
            Notification::fake();

            // Run the command multiple times (2-5 times)
            $runCount = $faker->numberBetween(2, 5);
            for ($run = 0; $run < $runCount; $run++) {
                $this->artisan('app:check-document-reviews')
                    ->assertSuccessful();
            }

            // Compute the expected due_date for this document
            $service = new DocumentReviewService();
            $document->load(['revisions', 'reviews']);
            $dueDate = $service->getDueDate($document);

            // Assert: The DocumentReviewNotification record for this document+due_date
            // has at most one non-null timestamp per type
            $notificationRecord = DocumentReviewNotification::where('document_id', $document->id)
                ->where('due_date', $dueDate->toDateString())
                ->first();

            // A record should exist (the command creates it via firstOrCreate)
            $this->assertNotNull(
                $notificationRecord,
                "Iteration {$iteration}: DocumentReviewNotification record should exist for document {$document->id} with due_date {$dueDate->toDateString()}"
            );

            // Based on the scenario, exactly one type should be marked as notified
            match ($scenario) {
                'panel' => $this->assertNotNull(
                    $notificationRecord->panel_notified_at,
                    "Iteration {$iteration}: panel_notified_at should be set for panel scenario (days remaining: {$daysRemaining})"
                ),
                'email' => $this->assertNotNull(
                    $notificationRecord->email_notified_at,
                    "Iteration {$iteration}: email_notified_at should be set for email scenario (days remaining: {$daysRemaining})"
                ),
                'overdue' => $this->assertNotNull(
                    $notificationRecord->overdue_notified_at,
                    "Iteration {$iteration}: overdue_notified_at should be set for overdue scenario (days remaining: {$daysRemaining})"
                ),
            };

            // Key idempotency assertion: Only ONE notification record exists for this document+due_date
            $recordCount = DocumentReviewNotification::where('document_id', $document->id)
                ->where('due_date', $dueDate->toDateString())
                ->count();

            $this->assertEquals(
                1,
                $recordCount,
                "Iteration {$iteration}: Should have exactly 1 DocumentReviewNotification record for document+due_date, got {$recordCount}"
            );

            // Assert that the notification was sent exactly once (not duplicated across runs)
            // The Notification::fake() captures all sent notifications
            $expectedNotificationType = \App\Notifications\DocumentReviewReminder::class;

            // Count how many times the notification was sent to the admin user
            $sentCount = 0;
            Notification::assertSentTo(
                $adminUser,
                $expectedNotificationType,
                function ($notification) use (&$sentCount) {
                    $sentCount++;
                    return true;
                }
            );

            // The notification should have been sent exactly once (idempotent - not once per run)
            $this->assertEquals(
                1,
                $sentCount,
                "Iteration {$iteration}: Notification should be sent exactly once to each recipient regardless of {$runCount} command runs. Scenario: {$scenario}, Days remaining: {$daysRemaining}"
            );

            Carbon::setTestNow();
        }
    }
}
