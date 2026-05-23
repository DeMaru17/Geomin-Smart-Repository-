<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentReview>
 */
class DocumentReviewFactory extends Factory
{
    protected $model = DocumentReview::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'review_date' => fake()->date(),
            'reviewer_id' => fake()->randomNumber(),
            'evaluation_status' => fake()->randomElement([
                'Perlu direvisi',
                'Perlu diperbaharui',
                'Perlu ditarik',
                'Perlu dimusnahkan',
                'Dipertahankan',
            ]),
            'remarks' => fake()->optional()->sentence(),
        ];
    }
}
