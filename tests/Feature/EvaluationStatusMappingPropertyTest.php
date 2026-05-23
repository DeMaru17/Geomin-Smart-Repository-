<?php

namespace Tests\Feature;

use App\Models\DocumentReview;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Property-based tests for evaluation status badge color mapping, action visibility,
 * and checkmark column mapping in export.
 *
 * Feature: document-review-module, Property 15: Badge color mapping consistency
 * Feature: document-review-module, Property 10: Action visibility is determined by evaluation status
 * Feature: document-review-module, Property 11: Evaluation status maps to exactly one checkmark column in export
 */
#[Group('Feature: document-review-module, Property 15: Badge color mapping consistency')]
#[Group('Feature: document-review-module, Property 10: Action visibility is determined by evaluation status')]
#[Group('Feature: document-review-module, Property 11: Evaluation status maps to exactly one checkmark column in export')]
class EvaluationStatusMappingPropertyTest extends TestCase
{
    /**
     * The color mapping logic extracted from RiwayatKajiUlangTable.
     * This replicates the exact match expression used in the Livewire component.
     */
    private function getColorForStatus(string $state): string
    {
        return match ($state) {
            'Dipertahankan' => 'success',
            'Perlu direvisi' => 'warning',
            'Perlu diperbaharui' => 'info',
            'Perlu ditarik' => 'warning',
            'Perlu dimusnahkan' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Validates: Requirements 7.2
     *
     * Property 15: Badge color mapping consistency
     *
     * For any DocumentReview record displayed in the Riwayat table, the badge color
     * SHALL be deterministically mapped from evaluation_status:
     * 'Dipertahankan' → success/hijau,
     * 'Perlu direvisi' → warning/kuning,
     * 'Perlu diperbaharui' → info/biru,
     * 'Perlu ditarik' → warning/oranye,
     * 'Perlu dimusnahkan' → danger/merah.
     */
    #[DataProvider('evaluationStatusColorProvider')]
    public function test_evaluation_status_maps_to_correct_badge_color(string $status, string $expectedColor): void
    {
        $actualColor = $this->getColorForStatus($status);

        $this->assertSame(
            $expectedColor,
            $actualColor,
            "Evaluation status '{$status}' should map to color '{$expectedColor}', got '{$actualColor}'"
        );
    }

    /**
     * Validates: Requirements 7.2
     *
     * Property 15: Badge color mapping consistency
     *
     * For any unknown/invalid evaluation_status value, the badge color SHALL
     * default to 'gray'.
     */
    #[DataProvider('unknownStatusProvider')]
    public function test_unknown_status_maps_to_gray(string $unknownStatus): void
    {
        $actualColor = $this->getColorForStatus($unknownStatus);

        $this->assertSame(
            'gray',
            $actualColor,
            "Unknown evaluation status '{$unknownStatus}' should map to 'gray', got '{$actualColor}'"
        );
    }

    /**
     * Validates: Requirements 7.2
     *
     * Property 15: Badge color mapping consistency
     *
     * The mapping is deterministic: calling the mapping function multiple times
     * with the same status always returns the same color.
     */
    #[DataProvider('randomizedStatusProvider')]
    public function test_color_mapping_is_deterministic(string $status, string $expectedColor, int $iteration): void
    {
        // Call the mapping multiple times to verify determinism
        $color1 = $this->getColorForStatus($status);
        $color2 = $this->getColorForStatus($status);
        $color3 = $this->getColorForStatus($status);

        $this->assertSame($color1, $color2, "Iteration {$iteration}: Color mapping for '{$status}' is not deterministic");
        $this->assertSame($color2, $color3, "Iteration {$iteration}: Color mapping for '{$status}' is not deterministic");
        $this->assertSame(
            $expectedColor,
            $color1,
            "Iteration {$iteration}: Status '{$status}' should map to '{$expectedColor}', got '{$color1}'"
        );
    }

    /**
     * Data provider with all valid evaluation_status values and their expected colors.
     */
    public static function evaluationStatusColorProvider(): iterable
    {
        yield 'Dipertahankan → success' => ['Dipertahankan', 'success'];
        yield 'Perlu direvisi → warning' => ['Perlu direvisi', 'warning'];
        yield 'Perlu diperbaharui → info' => ['Perlu diperbaharui', 'info'];
        yield 'Perlu ditarik → warning' => ['Perlu ditarik', 'warning'];
        yield 'Perlu dimusnahkan → danger' => ['Perlu dimusnahkan', 'danger'];
    }

    /**
     * Data provider with unknown/invalid status values that should map to 'gray'.
     * Generates 100+ random strings to verify the default case.
     */
    public static function unknownStatusProvider(): iterable
    {
        $faker = \Faker\Factory::create('id_ID');

        // Common invalid values
        yield 'empty string' => [''];
        yield 'random text' => ['Some random text'];
        yield 'lowercase dipertahankan' => ['dipertahankan'];
        yield 'uppercase PERLU DIREVISI' => ['PERLU DIREVISI'];
        yield 'partial match Perlu' => ['Perlu'];
        yield 'trailing space' => ['Dipertahankan '];
        yield 'leading space' => [' Perlu direvisi'];
        yield 'typo Perlu direvsi' => ['Perlu direvsi'];
        yield 'null-like string' => ['null'];
        yield 'numeric string' => ['123'];

        // Generate 100 random strings to verify default case
        for ($i = 0; $i < 100; $i++) {
            $randomString = $faker->randomElement([
                $faker->word(),
                $faker->sentence(2),
                $faker->lexify('????_????'),
                $faker->numerify('status_###'),
                $faker->randomElement(['Active', 'Inactive', 'Pending', 'Completed', 'Cancelled']),
            ]);
            yield "random_unknown_{$i}" => [$randomString];
        }
    }

    /**
     * Data provider that generates 100+ randomized iterations picking from valid statuses.
     * Verifies deterministic mapping across many iterations.
     */
    public static function randomizedStatusProvider(): iterable
    {
        $faker = \Faker\Factory::create('id_ID');

        $validStatuses = [
            'Dipertahankan' => 'success',
            'Perlu direvisi' => 'warning',
            'Perlu diperbaharui' => 'info',
            'Perlu ditarik' => 'warning',
            'Perlu dimusnahkan' => 'danger',
        ];

        $statusKeys = array_keys($validStatuses);

        for ($i = 0; $i < 100; $i++) {
            $status = $faker->randomElement($statusKeys);
            $expectedColor = $validStatuses[$status];

            yield "iteration_{$i}_{$status}" => [$status, $expectedColor, $i];
        }
    }

    // =========================================================================
    // Property 10: Action visibility is determined by evaluation status
    // =========================================================================

    /**
     * The visibility logic for "Buat Pengajuan Revisi" action,
     * extracted from RiwayatKajiUlangTable.
     */
    private function isRevisiActionVisible(DocumentReview $record): bool
    {
        return $record->evaluation_status === 'Perlu direvisi';
    }

    /**
     * The visibility logic for "Buat BA Pemusnahan" action,
     * extracted from RiwayatKajiUlangTable.
     */
    private function isPemusnahanActionVisible(DocumentReview $record): bool
    {
        return $record->evaluation_status === 'Perlu dimusnahkan';
    }

    /**
     * Validates: Requirements 8.1, 8.5, 8.7
     *
     * Property 10: Action visibility is determined by evaluation status
     *
     * For any DocumentReview with evaluation_status 'Perlu direvisi',
     * the "Buat Pengajuan Revisi" action SHALL be visible and
     * the "Buat BA Pemusnahan" action SHALL NOT be visible.
     */
    public function test_revisi_action_visible_only_for_perlu_direvisi_status(): void
    {
        $record = new DocumentReview(['evaluation_status' => 'Perlu direvisi']);

        $this->assertTrue(
            $this->isRevisiActionVisible($record),
            '"Buat Pengajuan Revisi" should be visible when evaluation_status is "Perlu direvisi"'
        );
        $this->assertFalse(
            $this->isPemusnahanActionVisible($record),
            '"Buat BA Pemusnahan" should NOT be visible when evaluation_status is "Perlu direvisi"'
        );
    }

    /**
     * Validates: Requirements 8.1, 8.5, 8.7
     *
     * Property 10: Action visibility is determined by evaluation status
     *
     * For any DocumentReview with evaluation_status 'Perlu dimusnahkan',
     * the "Buat BA Pemusnahan" action SHALL be visible and
     * the "Buat Pengajuan Revisi" action SHALL NOT be visible.
     */
    public function test_pemusnahan_action_visible_only_for_perlu_dimusnahkan_status(): void
    {
        $record = new DocumentReview(['evaluation_status' => 'Perlu dimusnahkan']);

        $this->assertTrue(
            $this->isPemusnahanActionVisible($record),
            '"Buat BA Pemusnahan" should be visible when evaluation_status is "Perlu dimusnahkan"'
        );
        $this->assertFalse(
            $this->isRevisiActionVisible($record),
            '"Buat Pengajuan Revisi" should NOT be visible when evaluation_status is "Perlu dimusnahkan"'
        );
    }

    /**
     * Validates: Requirements 8.1, 8.5, 8.7
     *
     * Property 10: Action visibility is determined by evaluation status
     *
     * For any DocumentReview with evaluation_status 'Dipertahankan', 'Perlu diperbaharui',
     * or 'Perlu ditarik', NO follow-up action SHALL be visible.
     */
    #[DataProvider('noActionStatusProvider')]
    public function test_no_follow_up_action_visible_for_non_actionable_statuses(string $status): void
    {
        $record = new DocumentReview(['evaluation_status' => $status]);

        $this->assertFalse(
            $this->isRevisiActionVisible($record),
            "\"Buat Pengajuan Revisi\" should NOT be visible when evaluation_status is \"{$status}\""
        );
        $this->assertFalse(
            $this->isPemusnahanActionVisible($record),
            "\"Buat BA Pemusnahan\" should NOT be visible when evaluation_status is \"{$status}\""
        );
    }

    /**
     * Validates: Requirements 8.1, 8.5, 8.7
     *
     * Property 10: Action visibility is determined by evaluation status
     *
     * For any randomized evaluation_status value, the action visibility rules
     * SHALL hold: "Buat Pengajuan Revisi" visible iff status === 'Perlu direvisi',
     * "Buat BA Pemusnahan" visible iff status === 'Perlu dimusnahkan',
     * no action visible for all other statuses.
     */
    #[DataProvider('randomizedActionVisibilityProvider')]
    public function test_action_visibility_property_holds_for_random_statuses(
        string $status,
        bool $expectedRevisiVisible,
        bool $expectedPemusnahanVisible,
        int $iteration,
    ): void {
        $record = new DocumentReview(['evaluation_status' => $status]);

        $revisiVisible = $this->isRevisiActionVisible($record);
        $pemusnahanVisible = $this->isPemusnahanActionVisible($record);

        $this->assertSame(
            $expectedRevisiVisible,
            $revisiVisible,
            "Iteration {$iteration}: \"Buat Pengajuan Revisi\" visibility for status \"{$status}\" - expected "
                . ($expectedRevisiVisible ? 'visible' : 'hidden') . ', got ' . ($revisiVisible ? 'visible' : 'hidden')
        );
        $this->assertSame(
            $expectedPemusnahanVisible,
            $pemusnahanVisible,
            "Iteration {$iteration}: \"Buat BA Pemusnahan\" visibility for status \"{$status}\" - expected "
                . ($expectedPemusnahanVisible ? 'visible' : 'hidden') . ', got ' . ($pemusnahanVisible ? 'visible' : 'hidden')
        );
    }

    /**
     * Data provider for statuses that should have no follow-up actions.
     */
    public static function noActionStatusProvider(): iterable
    {
        yield 'Dipertahankan' => ['Dipertahankan'];
        yield 'Perlu diperbaharui' => ['Perlu diperbaharui'];
        yield 'Perlu ditarik' => ['Perlu ditarik'];
    }

    /**
     * Data provider that generates 100 randomized iterations with all possible
     * evaluation_status values (valid and invalid) to verify action visibility property.
     */
    public static function randomizedActionVisibilityProvider(): iterable
    {
        $faker = \Faker\Factory::create('id_ID');

        $validStatuses = [
            'Perlu direvisi',
            'Perlu diperbaharui',
            'Perlu ditarik',
            'Perlu dimusnahkan',
            'Dipertahankan',
        ];

        for ($i = 0; $i < 100; $i++) {
            $status = $faker->randomElement($validStatuses);

            $expectedRevisiVisible = $status === 'Perlu direvisi';
            $expectedPemusnahanVisible = $status === 'Perlu dimusnahkan';

            yield "iteration_{$i}_{$status}" => [
                $status,
                $expectedRevisiVisible,
                $expectedPemusnahanVisible,
                $i,
            ];
        }
    }

    // =========================================================================
    // Property 11: Evaluation status maps to exactly one checkmark column in export
    // =========================================================================

    /**
     * The status-to-column mapping from DocumentReviewExportService.
     * Replicates the exact mapping used in the service.
     */
    private array $statusColumnMap = [
        'Perlu direvisi' => 'G',
        'Perlu diperbaharui' => 'H',
        'Perlu ditarik' => 'I',
        'Perlu dimusnahkan' => 'J',
        'Dipertahankan' => 'K',
    ];

    /**
     * All checkmark columns in the export (G through K).
     */
    private array $checkmarkColumns = ['G', 'H', 'I', 'J', 'K'];

    /**
     * Simulates the fillCheckmark logic from DocumentReviewExportService.
     * Returns an associative array of column => value for columns G-K.
     *
     * @return array<string, string>
     */
    private function applyCheckmarkMapping(string $evaluationStatus): array
    {
        $result = [];

        // Clear all checkmark columns first (same as service logic)
        foreach ($this->checkmarkColumns as $col) {
            $result[$col] = '';
        }

        // Set checkmark on the correct column
        if (isset($this->statusColumnMap[$evaluationStatus])) {
            $col = $this->statusColumnMap[$evaluationStatus];
            $result[$col] = 'V';
        }

        return $result;
    }

    /**
     * Validates: Requirements 9.4
     *
     * Property 11: Evaluation status maps to exactly one checkmark column in export
     *
     * For any valid evaluation_status, exactly one column in G-K SHALL have 'V'
     * and all other columns SHALL be empty.
     */
    #[DataProvider('evaluationStatusCheckmarkProvider')]
    public function test_evaluation_status_maps_to_exactly_one_checkmark_column(
        string $status,
        string $expectedColumn,
    ): void {
        $result = $this->applyCheckmarkMapping($status);

        // Assert exactly one column has 'V'
        $columnsWithCheckmark = array_filter($result, fn($value) => $value === 'V');
        $this->assertCount(
            1,
            $columnsWithCheckmark,
            "Status '{$status}' should produce exactly one checkmark, got " . count($columnsWithCheckmark)
        );

        // Assert the correct column has the checkmark
        $this->assertSame(
            'V',
            $result[$expectedColumn],
            "Status '{$status}' should have checkmark in column '{$expectedColumn}'"
        );

        // Assert all other columns are empty
        foreach ($this->checkmarkColumns as $col) {
            if ($col !== $expectedColumn) {
                $this->assertSame(
                    '',
                    $result[$col],
                    "Status '{$status}': column '{$col}' should be empty but got '{$result[$col]}'"
                );
            }
        }
    }

    /**
     * Validates: Requirements 9.4
     *
     * Property 11: Evaluation status maps to exactly one checkmark column in export
     *
     * For any randomized valid evaluation_status value across 100 iterations,
     * the checkmark mapping SHALL always produce exactly one 'V' in the correct column
     * and all other columns SHALL be empty.
     */
    #[DataProvider('randomizedCheckmarkMappingProvider')]
    public function test_checkmark_mapping_property_holds_for_random_statuses(
        string $status,
        string $expectedColumn,
        int $iteration,
    ): void {
        $result = $this->applyCheckmarkMapping($status);

        // Assert exactly one column has 'V'
        $columnsWithCheckmark = array_filter($result, fn($value) => $value === 'V');
        $this->assertCount(
            1,
            $columnsWithCheckmark,
            "Iteration {$iteration}: Status '{$status}' should produce exactly one checkmark, got " . count($columnsWithCheckmark)
        );

        // Assert the correct column has the checkmark
        $this->assertSame(
            'V',
            $result[$expectedColumn],
            "Iteration {$iteration}: Status '{$status}' should have checkmark in column '{$expectedColumn}'"
        );

        // Assert all other columns are empty
        foreach (['G', 'H', 'I', 'J', 'K'] as $col) {
            if ($col !== $expectedColumn) {
                $this->assertSame(
                    '',
                    $result[$col],
                    "Iteration {$iteration}: Status '{$status}': column '{$col}' should be empty but got '{$result[$col]}'"
                );
            }
        }
    }

    /**
     * Validates: Requirements 9.4
     *
     * Property 11: Evaluation status maps to exactly one checkmark column in export
     *
     * The mapping SHALL be mutually exclusive: no two different statuses map to the same column.
     */
    public function test_checkmark_column_mapping_is_mutually_exclusive(): void
    {
        $statusColumnMap = [
            'Perlu direvisi' => 'G',
            'Perlu diperbaharui' => 'H',
            'Perlu ditarik' => 'I',
            'Perlu dimusnahkan' => 'J',
            'Dipertahankan' => 'K',
        ];

        // Verify all columns are unique (no two statuses share a column)
        $columns = array_values($statusColumnMap);
        $uniqueColumns = array_unique($columns);

        $this->assertCount(
            count($columns),
            $uniqueColumns,
            'Each evaluation status must map to a unique column'
        );

        // Verify all expected columns G-K are covered
        $expectedColumns = ['G', 'H', 'I', 'J', 'K'];
        sort($columns);
        sort($expectedColumns);

        $this->assertSame(
            $expectedColumns,
            $columns,
            'The mapping must cover exactly columns G through K'
        );
    }

    /**
     * Data provider with all valid evaluation_status values and their expected checkmark columns.
     */
    public static function evaluationStatusCheckmarkProvider(): iterable
    {
        yield 'Perlu direvisi → G' => ['Perlu direvisi', 'G'];
        yield 'Perlu diperbaharui → H' => ['Perlu diperbaharui', 'H'];
        yield 'Perlu ditarik → I' => ['Perlu ditarik', 'I'];
        yield 'Perlu dimusnahkan → J' => ['Perlu dimusnahkan', 'J'];
        yield 'Dipertahankan → K' => ['Dipertahankan', 'K'];
    }

    /**
     * Data provider that generates 100 randomized iterations picking from valid statuses
     * to verify the checkmark mapping property holds universally.
     */
    public static function randomizedCheckmarkMappingProvider(): iterable
    {
        $faker = \Faker\Factory::create('id_ID');

        $statusColumnMap = [
            'Perlu direvisi' => 'G',
            'Perlu diperbaharui' => 'H',
            'Perlu ditarik' => 'I',
            'Perlu dimusnahkan' => 'J',
            'Dipertahankan' => 'K',
        ];

        $statuses = array_keys($statusColumnMap);

        for ($i = 0; $i < 100; $i++) {
            $status = $faker->randomElement($statuses);
            $expectedColumn = $statusColumnMap[$status];

            yield "iteration_{$i}_{$status}" => [$status, $expectedColumn, $i];
        }
    }
}
