<?php

namespace Tests\Feature;

use App\Filament\Resources\DocumentReviews\Pages\ListDocumentReviews;
use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\DocumentRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Property-based tests for review form validation.
 *
 * Feature: document-review-module, Property 6: Review form validation rejects incomplete submissions
 */
#[Group('Feature: document-review-module, Property 6: Review form validation rejects incomplete submissions')]
class FormValidationPropertyTest extends TestCase
{
    use RefreshDatabase;

    protected int $iterations = 100;

    /**
     * Validates: Requirements 4.8
     *
     * Property 6: Review form validation rejects incomplete submissions
     *
     * For any combination where at least one required field (document_id, review_date,
     * evaluation_status) is missing or empty, the form submission SHALL be rejected with
     * validation errors on the missing fields, and no DocumentReview record SHALL be created.
     */
    public function test_form_validation_rejects_incomplete_submissions(): void
    {
        $faker = \Faker\Factory::create('id_ID');

        // Create an admin user to authenticate
        $adminUser = User::factory()->create(['role' => 'admin']);

        // Create a document with a Published revision (so it appears in the select)
        $document = Document::factory()->create();
        DocumentRevision::factory()->create([
            'document_id' => $document->id,
            'status' => 'Published',
            'revision_date' => now()->subMonths(6),
        ]);

        $validStatuses = [
            'Perlu direvisi',
            'Perlu diperbaharui',
            'Perlu ditarik',
            'Perlu dimusnahkan',
            'Dipertahankan',
        ];

        // All possible combinations of missing required fields (at least one missing)
        // Required fields: document_id, review_date, evaluation_status
        $missingCombinations = [
            ['document_id'],
            ['review_date'],
            ['evaluation_status'],
            ['document_id', 'review_date'],
            ['document_id', 'evaluation_status'],
            ['review_date', 'evaluation_status'],
            ['document_id', 'review_date', 'evaluation_status'],
        ];

        $initialCount = DocumentReview::count();

        for ($iteration = 0; $iteration < $this->iterations; $iteration++) {
            // Randomly pick a combination of missing fields
            $missingFields = $faker->randomElement($missingCombinations);

            // Build form data: start with valid data, then null out missing fields
            $formData = [
                'document_id' => $document->id,
                'review_date' => $faker->date('Y-m-d'),
                'evaluation_status' => $faker->randomElement($validStatuses),
                'remarks' => $faker->optional()->sentence(),
            ];

            // Set missing fields to null
            foreach ($missingFields as $field) {
                $formData[$field] = null;
            }

            // Attempt to submit the form via Livewire test
            $this->actingAs($adminUser);

            $test = Livewire::test(ListDocumentReviews::class)
                ->callAction('catat_kaji_ulang', data: $formData)
                ->assertHasActionErrors($missingFields);

            // Assert no new DocumentReview record was created
            $this->assertEquals(
                $initialCount,
                DocumentReview::count(),
                "Iteration {$iteration}: No DocumentReview record should be created when fields [" . implode(', ', $missingFields) . '] are missing'
            );
        }
    }
}
