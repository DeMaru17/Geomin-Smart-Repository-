<?php

namespace Database\Seeders;

use App\Models\DocumentCategory;
use Illuminate\Database\Seeder;

class DocumentCategorySeeder extends Seeder
{
    /**
     * Seed kategori dokumen lab ISO Geomin.
     */
    public function run(): void
    {
        $categories = [
            ['code' => 'PK', 'name' => 'Prosedur Kerja'],
            ['code' => 'IK', 'name' => 'Instruksi Kerja'],
            ['code' => 'F', 'name' => 'Form'],
            ['code' => 'PM', 'name' => 'Panduan Mutu'],
        ];

        foreach ($categories as $category) {
            DocumentCategory::updateOrCreate(
                ['code' => $category['code']],
                ['name' => $category['name']],
            );
        }

        $this->command->info('Seeded ' . count($categories) . ' document categories.');
    }
}
