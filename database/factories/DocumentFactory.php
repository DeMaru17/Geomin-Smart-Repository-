<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_number' => fake()->unique()->bothify('GSR-####-???'),
            'title' => fake()->sentence(4),
            'category_id' => DocumentCategory::factory(),
            'department_id' => Department::factory(),
            'is_external' => fake()->boolean(20),
            'retention_period_months' => 36,
        ];
    }
}
