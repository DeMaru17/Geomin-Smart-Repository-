<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\QualityRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QualityRecord>
 */
class QualityRecordFactory extends Factory
{
    protected $model = QualityRecord::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'record_name' => fake()->sentence(3),
            'record_date' => fake()->date(),
            'confidentiality' => fake()->randomElement(['Sangat Rahasia', 'Rahasia', 'Biasa']),
            'description' => fake()->optional()->paragraph(),
            'physical_location' => fake()->optional()->word(),
            'file_path' => 'quality-records/' . fake()->uuid() . '.pdf',
            'expiration_date' => fake()->dateTimeBetween('now', '+3 years'),
            'status' => 'Aktif',
            'uploader_id' => User::factory(),
        ];
    }
}
