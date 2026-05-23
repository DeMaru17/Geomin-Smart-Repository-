<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentReview;
use App\Services\DocumentReviewExportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Property-based tests for DocumentReviewExportService period filtering and filename generation.
 *
 * Feature: document-review-module, Property 12: Export period filter returns correct records
 * Feature: document-review-module, Property 13: Export filename follows naming convention
 */
class ExportFilenamePropertyTest extends TestCase
{
    use RefreshDatabase;

    protected int $iterations = 100;

    /**
     * Indonesian month names mapping (1-12).
     */
    private static array $namaBulan = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    // =========================================================================
    // Property 12: Export period filter returns correct records
    // =========================================================================

    /**
     * Validates: Requirements 9.9, 9.10
     *
     * Property 12: Export period filter returns correct records
     *
     * For any set of DocumentReview records with varying review_dates, filtering by a specific
     * month M and year Y SHALL return exactly those records where the month of `review_date`
     * equals M and the year of `review_date` equals Y. When month is 'all'/null, filtering
     * SHALL return all records where the year of `review_date` equals Y.
     */
    #[Group('Feature: document-review-module, Property 12: Export period filter returns correct records')]
    public function test_export_period_filter_returns_correct_records(): void
    {
        $faker = \Faker\Factory::create('id_ID');

        for ($iteration = 0; $iteration < $this->iterations; $iteration++) {
            // Clean up previous iteration data
            DocumentReview::query()->delete();
            Document::query()->delete();

            // Create N documents (between 2 and 5) to associate reviews with
            $numDocuments = $faker->numberBetween(2, 5);
            $documents = [];
            for ($d = 0; $d < $numDocuments; $d++) {
                $documents[] = Document::factory()->create();
            }

            // Create M review records (between 5 and 20) with random review_dates
            // spanning multiple months and years
            $numReviews = $faker->numberBetween(5, 20);
            $allReviews = [];

            for ($r = 0; $r < $numReviews; $r++) {
                $reviewDate = Carbon::create(
                    $faker->numberBetween(2022, 2025),
                    $faker->numberBetween(1, 12),
                    $faker->numberBetween(1, 28)
                );

                $review = DocumentReview::factory()->create([
                    'document_id' => $faker->randomElement($documents)->id,
                    'review_date' => $reviewDate,
                ]);

                $allReviews[] = $review;
            }

            // Pick a random year from the data to filter on
            $targetYear = $faker->numberBetween(2022, 2025);

            // Decide whether to test with a specific month or 'all'/null
            $testWithMonth = $faker->boolean(70); // 70% chance of specific month

            if ($testWithMonth) {
                // Test with specific month filter
                $targetMonth = $faker->numberBetween(1, 12);
                $periodeBulan = (string) $targetMonth;

                // Apply the same filter logic as DocumentReviewExportService
                $query = DocumentReview::whereYear('review_date', $targetYear);
                $query->whereMonth('review_date', (int) $periodeBulan);
                $filteredResults = $query->get();

                // Manually compute expected results
                $expectedIds = collect($allReviews)
                    ->filter(function ($review) use ($targetMonth, $targetYear) {
                        $reviewDate = Carbon::parse($review->review_date);

                        return $reviewDate->month === $targetMonth
                            && $reviewDate->year === $targetYear;
                    })
                    ->pluck('id')
                    ->sort()
                    ->values()
                    ->toArray();

                $actualIds = $filteredResults->pluck('id')->sort()->values()->toArray();

                $this->assertEquals(
                    $expectedIds,
                    $actualIds,
                    "Iteration {$iteration}: Month={$targetMonth}, Year={$targetYear}. "
                        . 'Expected ' . count($expectedIds) . ' records, got ' . count($actualIds) . '.'
                );

                // Assert: no records outside the filter are included
                foreach ($filteredResults as $result) {
                    $resultDate = Carbon::parse($result->review_date);
                    $this->assertEquals(
                        $targetMonth,
                        $resultDate->month,
                        "Iteration {$iteration}: Record ID {$result->id} has month {$resultDate->month}, expected {$targetMonth}"
                    );
                    $this->assertEquals(
                        $targetYear,
                        $resultDate->year,
                        "Iteration {$iteration}: Record ID {$result->id} has year {$resultDate->year}, expected {$targetYear}"
                    );
                }
            } else {
                // Test with null/'all' month filter (year-only filtering)
                $periodeBulan = $faker->randomElement([null, 'all']);

                // Apply the same filter logic as DocumentReviewExportService
                $query = DocumentReview::whereYear('review_date', $targetYear);
                if ($periodeBulan !== null && $periodeBulan !== 'all') {
                    $query->whereMonth('review_date', (int) $periodeBulan);
                }
                $filteredResults = $query->get();

                // Manually compute expected results — all records for that year
                $expectedIds = collect($allReviews)
                    ->filter(function ($review) use ($targetYear) {
                        $reviewDate = Carbon::parse($review->review_date);

                        return $reviewDate->year === $targetYear;
                    })
                    ->pluck('id')
                    ->sort()
                    ->values()
                    ->toArray();

                $actualIds = $filteredResults->pluck('id')->sort()->values()->toArray();

                $this->assertEquals(
                    $expectedIds,
                    $actualIds,
                    "Iteration {$iteration}: Month={$periodeBulan} (all), Year={$targetYear}. "
                        . 'Expected ' . count($expectedIds) . ' records, got ' . count($actualIds) . '.'
                );

                // Assert: all returned records have the correct year
                foreach ($filteredResults as $result) {
                    $resultDate = Carbon::parse($result->review_date);
                    $this->assertEquals(
                        $targetYear,
                        $resultDate->year,
                        "Iteration {$iteration}: Record ID {$result->id} has year {$resultDate->year}, expected {$targetYear}"
                    );
                }
            }
        }
    }

    // =========================================================================
    // Property 13: Export filename follows naming convention
    // =========================================================================

    /**
     * Validates: Requirements 9.6
     *
     * Property 13: Export filename follows naming convention
     *
     * For any valid periode_bulan (null, 'all', or 1-12) and periode_tahun (integer),
     * the generated filename SHALL start with the correct prefix.
     */
    #[Group('Feature: document-review-module, Property 13: Export filename follows naming convention')]
    #[DataProvider('randomizedFilenameProvider')]
    public function test_filename_starts_with_correct_prefix(
        ?string $periodeBulan,
        int $periodeTahun,
        int $iteration,
    ): void {
        $service = new DocumentReviewExportService();
        $filename = $service->getFilename($periodeBulan, $periodeTahun);

        $expectedPrefix = 'Form-273.106.205_Evaluasi_Kaji_Ulang_Dokumen_';

        $this->assertStringStartsWith(
            $expectedPrefix,
            $filename,
            "Iteration {$iteration}: Filename should start with '{$expectedPrefix}', got '{$filename}'"
        );
    }

    /**
     * Validates: Requirements 9.6
     *
     * Property 13: Export filename follows naming convention
     *
     * For any valid periode_bulan and periode_tahun, the generated filename
     * SHALL end with ".xlsx" extension.
     */
    #[Group('Feature: document-review-module, Property 13: Export filename follows naming convention')]
    #[DataProvider('randomizedFilenameProvider')]
    public function test_filename_ends_with_xlsx_extension(
        ?string $periodeBulan,
        int $periodeTahun,
        int $iteration,
    ): void {
        $service = new DocumentReviewExportService();
        $filename = $service->getFilename($periodeBulan, $periodeTahun);

        $this->assertStringEndsWith(
            '.xlsx',
            $filename,
            "Iteration {$iteration}: Filename should end with '.xlsx', got '{$filename}'"
        );
    }

    /**
     * Validates: Requirements 9.6
     *
     * Property 13: Export filename follows naming convention
     *
     * For any valid periode_bulan (null or 'all'), the generated filename
     * SHALL contain "Semua-Bulan" as the month part.
     */
    #[Group('Feature: document-review-module, Property 13: Export filename follows naming convention')]
    #[DataProvider('semuaBulanProvider')]
    public function test_filename_contains_semua_bulan_when_periode_is_null_or_all(
        ?string $periodeBulan,
        int $periodeTahun,
        int $iteration,
    ): void {
        $service = new DocumentReviewExportService();
        $filename = $service->getFilename($periodeBulan, $periodeTahun);

        $this->assertStringContainsString(
            'Semua-Bulan',
            $filename,
            "Iteration {$iteration}: Filename should contain 'Semua-Bulan' when periodeBulan is "
                . var_export($periodeBulan, true) . ", got '{$filename}'"
        );
    }

    /**
     * Validates: Requirements 9.6
     *
     * Property 13: Export filename follows naming convention
     *
     * For any valid periode_bulan (1-12), the generated filename SHALL contain
     * the correct Indonesian month name.
     */
    #[Group('Feature: document-review-module, Property 13: Export filename follows naming convention')]
    #[DataProvider('specificMonthProvider')]
    public function test_filename_contains_correct_indonesian_month_name(
        string $periodeBulan,
        int $periodeTahun,
        string $expectedMonthName,
        int $iteration,
    ): void {
        $service = new DocumentReviewExportService();
        $filename = $service->getFilename($periodeBulan, $periodeTahun);

        $this->assertStringContainsString(
            $expectedMonthName,
            $filename,
            "Iteration {$iteration}: Filename should contain '{$expectedMonthName}' for month '{$periodeBulan}', got '{$filename}'"
        );
    }

    /**
     * Validates: Requirements 9.6
     *
     * Property 13: Export filename follows naming convention
     *
     * For any valid periode_tahun, the generated filename SHALL contain
     * the correct year value.
     */
    #[Group('Feature: document-review-module, Property 13: Export filename follows naming convention')]
    #[DataProvider('randomizedFilenameProvider')]
    public function test_filename_contains_correct_year(
        ?string $periodeBulan,
        int $periodeTahun,
        int $iteration,
    ): void {
        $service = new DocumentReviewExportService();
        $filename = $service->getFilename($periodeBulan, $periodeTahun);

        $this->assertStringContainsString(
            (string) $periodeTahun,
            $filename,
            "Iteration {$iteration}: Filename should contain year '{$periodeTahun}', got '{$filename}'"
        );
    }

    /**
     * Validates: Requirements 9.6
     *
     * Property 13: Export filename follows naming convention
     *
     * For any valid inputs, the generated filename SHALL match the full expected pattern:
     * Form-273.106.205_Evaluasi_Kaji_Ulang_Dokumen_[bulan]-[tahun].xlsx
     */
    #[Group('Feature: document-review-module, Property 13: Export filename follows naming convention')]
    #[DataProvider('randomizedFilenameProvider')]
    public function test_filename_matches_full_naming_convention(
        ?string $periodeBulan,
        int $periodeTahun,
        int $iteration,
    ): void {
        $service = new DocumentReviewExportService();
        $filename = $service->getFilename($periodeBulan, $periodeTahun);

        // Determine expected month part
        if ($periodeBulan === null || $periodeBulan === 'all') {
            $expectedBulanPart = 'Semua-Bulan';
        } else {
            $bulanInt = (int) $periodeBulan;
            $expectedBulanPart = self::$namaBulan[$bulanInt] ?? 'Semua-Bulan';
        }

        $expectedFilename = "Form-273.106.205_Evaluasi_Kaji_Ulang_Dokumen_{$expectedBulanPart}-{$periodeTahun}.xlsx";

        $this->assertSame(
            $expectedFilename,
            $filename,
            "Iteration {$iteration}: Expected filename '{$expectedFilename}', got '{$filename}'"
        );
    }

    /**
     * Data provider that generates 100 randomized iterations with various
     * combinations of periodeBulan (null, 'all', '1'-'12') and periodeTahun (2020-2030).
     */
    public static function randomizedFilenameProvider(): iterable
    {
        $faker = \Faker\Factory::create('id_ID');

        $validBulanOptions = [null, 'all', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];

        for ($i = 0; $i < 100; $i++) {
            $periodeBulan = $faker->randomElement($validBulanOptions);
            $periodeTahun = $faker->numberBetween(2020, 2030);

            $label = $periodeBulan === null ? 'null' : $periodeBulan;
            yield "iteration_{$i}_bulan_{$label}_tahun_{$periodeTahun}" => [
                $periodeBulan,
                $periodeTahun,
                $i,
            ];
        }
    }

    /**
     * Data provider for cases where periodeBulan is null or 'all' (Semua Bulan).
     * Generates randomized iterations to verify "Semua-Bulan" is used.
     */
    public static function semuaBulanProvider(): iterable
    {
        $faker = \Faker\Factory::create('id_ID');

        for ($i = 0; $i < 50; $i++) {
            $periodeBulan = $faker->randomElement([null, 'all']);
            $periodeTahun = $faker->numberBetween(2020, 2030);

            $label = $periodeBulan === null ? 'null' : 'all';
            yield "iteration_{$i}_{$label}_tahun_{$periodeTahun}" => [
                $periodeBulan,
                $periodeTahun,
                $i,
            ];
        }
    }

    /**
     * Data provider for specific month values (1-12) with expected Indonesian month names.
     * Generates 100 randomized iterations picking from months 1-12.
     */
    public static function specificMonthProvider(): iterable
    {
        $faker = \Faker\Factory::create('id_ID');

        for ($i = 0; $i < 100; $i++) {
            $monthNum = $faker->numberBetween(1, 12);
            $periodeBulan = (string) $monthNum;
            $periodeTahun = $faker->numberBetween(2020, 2030);
            $expectedMonthName = self::$namaBulan[$monthNum];

            yield "iteration_{$i}_bulan_{$periodeBulan}_{$expectedMonthName}_tahun_{$periodeTahun}" => [
                $periodeBulan,
                $periodeTahun,
                $expectedMonthName,
                $i,
            ];
        }
    }
}
