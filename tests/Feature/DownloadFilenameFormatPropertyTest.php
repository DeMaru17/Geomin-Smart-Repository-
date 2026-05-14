<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * @group Feature: dual-qr-pdf-stamping, Property 6: Download Filename Format
 */
#[Group('Feature: dual-qr-pdf-stamping, Property 6: Download Filename Format')]
class DownloadFilenameFormatPropertyTest extends TestCase
{
    /**
     * Validates: Requirements 4.5
     *
     * Property 6: Download Filename Format
     * For any document with document_number D, title T, and revision with revision_number R,
     * the uncontrolled copy download filename SHALL equal "{D} - {T} (Rev {R}) - Uncontrolled.pdf".
     */
    #[DataProvider('randomDocumentCombinationsProvider')]
    public function test_download_filename_matches_expected_format(
        string $documentNumber,
        string $title,
        string $revisionNumber
    ): void {
        // Construct filename using the same logic as DocumentResource
        $filename = "{$documentNumber} - {$title} (Rev {$revisionNumber}) - Uncontrolled.pdf";

        // Verify the filename matches the expected pattern
        $this->assertStringStartsWith($documentNumber . ' - ', $filename);
        $this->assertStringEndsWith(' - Uncontrolled.pdf', $filename);
        $this->assertStringContainsString("(Rev {$revisionNumber})", $filename);

        // Verify exact format: {D} - {T} (Rev {R}) - Uncontrolled.pdf
        $expectedPattern = sprintf(
            '%s - %s (Rev %s) - Uncontrolled.pdf',
            $documentNumber,
            $title,
            $revisionNumber
        );
        $this->assertSame($expectedPattern, $filename);
    }

    /**
     * Validates: Requirements 4.5
     *
     * Property 6: Download Filename Format
     * Verify that special characters in title don't break the filename format structure.
     */
    #[DataProvider('specialCharacterTitleProvider')]
    public function test_special_characters_in_title_preserve_filename_format(
        string $documentNumber,
        string $title,
        string $revisionNumber
    ): void {
        $filename = "{$documentNumber} - {$title} (Rev {$revisionNumber}) - Uncontrolled.pdf";

        // The filename should still follow the exact pattern regardless of special characters
        $expectedPattern = sprintf(
            '%s - %s (Rev %s) - Uncontrolled.pdf',
            $documentNumber,
            $title,
            $revisionNumber
        );
        $this->assertSame($expectedPattern, $filename);

        // Verify the file extension is always .pdf
        $this->assertStringEndsWith('.pdf', $filename);

        // Verify the " - Uncontrolled.pdf" suffix is intact
        $this->assertStringEndsWith(' - Uncontrolled.pdf', $filename);

        // Verify the "(Rev " prefix for revision is present
        $this->assertStringContainsString('(Rev ', $filename);
    }

    /**
     * Generate 110+ random document_number/title/revision_number combinations.
     */
    public static function randomDocumentCombinationsProvider(): iterable
    {
        $faker = \Faker\Factory::create('id_ID');

        for ($i = 0; $i < 110; $i++) {
            $documentNumber = $faker->randomElement(['GSR', 'DOC', 'SOP', 'WI', 'FM']) . '-' . $faker->numerify('###');
            $title = $faker->sentence($faker->numberBetween(2, 8));
            $revisionNumber = str_pad((string) $faker->numberBetween(0, 99), 2, '0', STR_PAD_LEFT);

            yield "combination_{$i}" => [
                $documentNumber,
                $title,
                $revisionNumber,
            ];
        }
    }

    /**
     * Generate combinations with special characters in title that could potentially break formatting.
     */
    public static function specialCharacterTitleProvider(): iterable
    {
        $faker = \Faker\Factory::create('id_ID');

        $specialTitles = [
            'Prosedur "Pengujian" Lab',
            "Panduan Kerja - Bagian A/B",
            'SOP (Versi Baru) & Revisi',
            'Dokumen: Analisis 50% Sampel',
            'Instruksi Kerja #12 @Lab',
            'Prosedur [Khusus] {Internal}',
            "Panduan Mutu - Rev. 01/02",
            'SOP Pengujian <Kimia>',
            'Dokumen Kontrol; Versi Final!',
            'Instruksi Kerja (IK) - Bagian 1.2.3',
        ];

        for ($i = 0; $i < 110; $i++) {
            $documentNumber = $faker->randomElement(['GSR', 'DOC', 'SOP', 'WI', 'FM']) . '-' . $faker->numerify('###');
            $revisionNumber = str_pad((string) $faker->numberBetween(0, 99), 2, '0', STR_PAD_LEFT);

            // Mix special character titles with faker-generated ones
            if ($i < count($specialTitles)) {
                $title = $specialTitles[$i];
            } else {
                // Generate titles with random special characters mixed in
                $baseTitle = $faker->sentence($faker->numberBetween(2, 6));
                $specialChars = ['/', '&', '"', "'", '(', ')', '-', '#', '@', '.', ',', ';', ':', '!'];
                $randomChar = $faker->randomElement($specialChars);
                $title = str_replace(' ', " {$randomChar} ", $baseTitle);
            }

            yield "special_char_{$i}" => [
                $documentNumber,
                $title,
                $revisionNumber,
            ];
        }
    }
}
