<?php

namespace Tests\Feature;

use App\Models\DocumentRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * @group Feature: dual-qr-pdf-stamping, Property 1: QR Token Generation Invariant
 */
#[Group('Feature: dual-qr-pdf-stamping, Property 1: QR Token Generation Invariant')]
class QrTokenGenerationInvariantTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Validates: Requirements 1.1, 1.3
     *
     * Property 1: QR Token Generation Invariant
     * For any DocumentRevision creation where qr_token is null, the system SHALL populate
     * qr_token with a string of exactly 16 characters where each character is alphanumeric.
     */
    #[DataProvider('randomRevisionPayloadsProvider')]
    public function test_auto_generated_qr_token_is_exactly_16_alphanumeric_characters(array $payload): void
    {
        $revision = DocumentRevision::factory()->create($payload);

        $this->assertNotNull($revision->qr_token);
        $this->assertSame(16, strlen($revision->qr_token));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{16}$/', $revision->qr_token);
    }

    /**
     * Validates: Requirements 1.1, 1.3
     *
     * Property 1: QR Token Generation Invariant
     * For any DocumentRevision creation where qr_token is already non-null,
     * the existing value SHALL be preserved unchanged.
     */
    #[DataProvider('existingTokenPayloadsProvider')]
    public function test_existing_non_null_qr_token_is_preserved_unchanged(string $existingToken): void
    {
        $revision = DocumentRevision::factory()->create([
            'qr_token' => $existingToken,
        ]);

        $this->assertSame($existingToken, $revision->qr_token);
    }

    /**
     * Generate 100+ random revision payloads without qr_token set.
     */
    public static function randomRevisionPayloadsProvider(): iterable
    {
        $faker = \Faker\Factory::create();

        for ($i = 0; $i < 110; $i++) {
            yield "revision_payload_{$i}" => [
                [
                    'revision_number' => $faker->numerify('0#'),
                    'file_path' => 'revisions/' . $faker->uuid() . '.pdf',
                    'status' => $faker->randomElement(['Draft', 'In_Review', 'Approved', 'Published', 'Obsolete']),
                    'change_summary' => $faker->optional()->sentence(),
                    // qr_token intentionally omitted to test auto-generation
                ],
            ];
        }
    }

    /**
     * Generate 100+ existing non-null tokens to verify preservation.
     */
    public static function existingTokenPayloadsProvider(): iterable
    {
        $faker = \Faker\Factory::create();

        for ($i = 0; $i < 110; $i++) {
            // Generate various alphanumeric tokens of different patterns
            $token = $faker->regexify('[a-zA-Z0-9]{16}');
            yield "existing_token_{$i}" => [$token];
        }
    }
}
