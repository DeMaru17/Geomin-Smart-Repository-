<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentReview;
use App\Models\DocumentRevision;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // ============================================================
        // 1. Users (disimpan di koneksi mysql_hris)
        // ============================================================
        $admin = User::factory()->create([
            'name' => 'Administrator',
            'email' => 'admin@geomin.co.id',
            'role' => 'admin',
            'jabatan' => 'admin',
        ]);

        $manajemen = User::factory()->create([
            'name' => 'Manajer Mutu',
            'email' => 'manajemen@geomin.co.id',
            'role' => 'manajemen',
            'jabatan' => 'manajemen',
        ]);

        $reviewer = User::factory()->create([
            'name' => 'Reviewer Dokumen',
            'email' => 'reviewer@geomin.co.id',
            'role' => 'personil',
            'jabatan' => 'analis',
        ]);

        $regularUsers = User::factory(5)->create([
            'role' => 'personil',
        ]);

        // ============================================================
        // 2. Departments
        // ============================================================
        $departments = collect([
            ['code' => 'LAB', 'name' => 'Laboratorium'],
            ['code' => 'QA', 'name' => 'Penjaminan Mutu'],
            ['code' => 'PROD', 'name' => 'Produksi'],
            ['code' => 'HSE', 'name' => 'Kesehatan, Keselamatan & Lingkungan'],
            ['code' => 'HR', 'name' => 'Sumber Daya Manusia'],
        ])->map(fn($dept) => Department::create($dept));

        // ============================================================
        // 3. Document Categories
        // ============================================================
        $categories = collect([
            ['code' => 'SOP', 'name' => 'Standard Operating Procedure'],
            ['code' => 'WI', 'name' => 'Work Instruction'],
            ['code' => 'FM', 'name' => 'Form / Formulir'],
            ['code' => 'MNL', 'name' => 'Manual Mutu'],
            ['code' => 'EXT', 'name' => 'Dokumen Eksternal'],
        ])->map(fn($cat) => DocumentCategory::create($cat));

        // ============================================================
        // 4. Documents (internal & external)
        // ============================================================
        $internalDocuments = Document::factory(10)->create([
            'category_id' => fn() => $categories->whereIn('code', ['SOP', 'WI', 'FM', 'MNL'])->random()->id,
            'department_id' => fn() => $departments->random()->id,
            'is_external' => false,
            'publication_year' => fn() => fake()->numberBetween(2020, 2025),
            'retention_period_months' => 36,
        ]);

        $externalDocuments = Document::factory(3)->create([
            'category_id' => $categories->firstWhere('code', 'EXT')->id,
            'department_id' => fn() => $departments->random()->id,
            'is_external' => true,
            'publication_year' => fn() => fake()->numberBetween(2020, 2025),
            'retention_period_months' => 36,
        ]);

        $allDocuments = $internalDocuments->merge($externalDocuments);

        // ============================================================
        // 5. Document Revisions
        // ============================================================
        $allDocuments->each(function (Document $document) use ($admin) {
            // Revisi pertama — Published
            DocumentRevision::factory()->create([
                'document_id' => $document->id,
                'revision_number' => '00',
                'status' => 'Published',
                'revision_date' => fake()->dateTimeBetween('-2 years', '-6 months'),
                'change_summary' => 'Penerbitan awal dokumen',
                'uploader_id' => $admin->id,
            ]);

            // Sebagian dokumen punya revisi kedua (Draft)
            if (fake()->boolean(40)) {
                DocumentRevision::factory()->create([
                    'document_id' => $document->id,
                    'revision_number' => '01',
                    'status' => 'Draft',
                    'revision_date' => fake()->dateTimeBetween('-3 months', 'now'),
                    'change_summary' => fake()->sentence(),
                    'uploader_id' => $admin->id,
                ]);
            }
        });

        // ============================================================
        // 6. Document Reviews
        // ============================================================
        $allDocuments->each(function (Document $document) use ($reviewer, $manajemen, $regularUsers) {
            // Setiap dokumen punya 1-3 review
            $reviewCount = fake()->numberBetween(1, 3);
            $reviewers = collect([$reviewer, $manajemen])->merge($regularUsers)->shuffle();

            for ($i = 0; $i < $reviewCount; $i++) {
                DocumentReview::factory()->create([
                    'document_id' => $document->id,
                    'reviewer_id' => $reviewers[$i % $reviewers->count()]->id,
                    'review_date' => fake()->dateTimeBetween('-1 year', 'now'),
                ]);
            }
        });
    }
}
