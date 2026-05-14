<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentRevision>
 */
class DocumentRevisionFactory extends Factory
{
    protected $model = DocumentRevision::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'revision_number' => fake()->numerify('0#'),
            'file_path' => 'revisions/' . fake()->uuid() . '.pdf',
            'status' => 'Draft',
            'change_summary' => fake()->optional()->sentence(),
            'qr_token' => null,
            'uploader_id' => 1,
        ];
    }

    /**
     * Set a specific QR token value.
     */
    public function withQrToken(string $token): static
    {
        return $this->state(fn(array $attributes) => [
            'qr_token' => $token,
        ]);
    }
}
