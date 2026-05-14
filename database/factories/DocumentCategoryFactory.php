<?php

namespace Database\Factories;

use App\Models\DocumentCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentCategory>
 */
class DocumentCategoryFactory extends Factory
{
    protected $model = DocumentCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('CAT-##??'),
            'name' => fake()->unique()->words(2, true),
        ];
    }
}
