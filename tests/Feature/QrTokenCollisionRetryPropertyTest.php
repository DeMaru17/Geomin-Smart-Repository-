<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Department;
use App\Models\DocumentRevision;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * @group Feature: dual-qr-pdf-stamping, Property 2: QR Token Collision Retry
 */
class QrTokenCollisionRetryPropertyTest extends TestCase
{
    use DatabaseTransactions;

    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();

        // Create prerequisite records for foreign keys (rolled back after each test)
        $uniqueSuffix = uniqid();

        $department = Department::create([
            'code' => 'TST' . $uniqueSuffix,
            'name' => 'Test Department ' . $uniqueSuffix,
        ]);

        $category = DocumentCategory::create([
            'code' => 'TST' . $uniqueSuffix,
            'name' => 'Test Category ' . $uniqueSuffix,
        ]);

        $this->document = Document::create([
            'document_number' => 'TEST-' . $uniqueSuffix,
            'title' => 'Test Document',
            'category_id' => $category->id,
            'department_id' => $department->id,
            'is_external' => false,
            'retention_period_months' => 36,
        ]);
    }

    protected function tearDown(): void
    {
        // Reset the random string generator after each test
        Str::createRandomStringsNormally();
        parent::tearDown();
    }

    /**
     * Property 2: QR Token Collision Retry
     * Validates: Requirements 1.5
     *
     * When all 3 token generation attempts collide with existing tokens,
     * the system SHALL throw a RuntimeException.
     */
    public function test_throws_runtime_exception_after_three_consecutive_collisions(): void
    {
        $collidingToken = 'EXISTINGTOKEN123';

        // Pre-insert a revision with the colliding token (bypass the creating event)
        DocumentRevision::withoutEvents(function () use ($collidingToken) {
            DocumentRevision::create([
                'document_id' => $this->document->id,
                'revision_number' => '00',
                'file_path' => 'documents/existing.pdf',
                'status' => 'Draft',
                'qr_token' => $collidingToken,
                'uploader_id' => 1,
            ]);
        });

        // Force Str::random to always return the colliding token
        Str::createRandomStringsUsing(fn() => $collidingToken);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to generate unique QR token after 3 attempts.');

        DocumentRevision::create([
            'document_id' => $this->document->id,
            'revision_number' => '01',
            'file_path' => 'documents/new.pdf',
            'status' => 'Draft',
            'uploader_id' => 1,
        ]);
    }

    /**
     * Property 2: QR Token Collision Retry
     * Validates: Requirements 1.5
     *
     * When the first attempt collides but a subsequent attempt succeeds,
     * the revision SHALL be created with the successful token.
     */
    public function test_succeeds_on_second_attempt_after_first_collision(): void
    {
        $collidingToken = 'COLLIDINGTOKEN01';
        $successToken = 'SUCCESSTOKEN0002';

        // Pre-insert a revision with the colliding token
        DocumentRevision::withoutEvents(function () use ($collidingToken) {
            DocumentRevision::create([
                'document_id' => $this->document->id,
                'revision_number' => '00',
                'file_path' => 'documents/existing.pdf',
                'status' => 'Draft',
                'qr_token' => $collidingToken,
                'uploader_id' => 1,
            ]);
        });

        // First call returns colliding token, second call returns unique token
        $callCount = 0;
        Str::createRandomStringsUsing(function () use ($collidingToken, $successToken, &$callCount) {
            $callCount++;
            return $callCount === 1 ? $collidingToken : $successToken;
        });

        $revision = DocumentRevision::create([
            'document_id' => $this->document->id,
            'revision_number' => '01',
            'file_path' => 'documents/new.pdf',
            'status' => 'Draft',
            'uploader_id' => 1,
        ]);

        $this->assertEquals($successToken, $revision->qr_token);
        $this->assertGreaterThanOrEqual(2, $callCount, 'Should have retried at least once');
    }

    /**
     * Property 2: QR Token Collision Retry
     * Validates: Requirements 1.5
     *
     * When the first two attempts collide but the third succeeds,
     * the revision SHALL be created with the third token.
     */
    public function test_succeeds_on_third_attempt_after_two_collisions(): void
    {
        $collidingToken = 'COLLIDINGTOKEN01';
        $successToken = 'THIRDATTEMPTOK01';

        // Pre-insert a revision with the colliding token
        DocumentRevision::withoutEvents(function () use ($collidingToken) {
            DocumentRevision::create([
                'document_id' => $this->document->id,
                'revision_number' => '00',
                'file_path' => 'documents/existing.pdf',
                'status' => 'Draft',
                'qr_token' => $collidingToken,
                'uploader_id' => 1,
            ]);
        });

        // First two calls return colliding token, third returns unique token
        $callCount = 0;
        Str::createRandomStringsUsing(function () use ($collidingToken, $successToken, &$callCount) {
            $callCount++;
            return $callCount <= 2 ? $collidingToken : $successToken;
        });

        $revision = DocumentRevision::create([
            'document_id' => $this->document->id,
            'revision_number' => '01',
            'file_path' => 'documents/new.pdf',
            'status' => 'Draft',
            'uploader_id' => 1,
        ]);

        $this->assertEquals($successToken, $revision->qr_token);
        $this->assertEquals(3, $callCount, 'Should have made exactly 3 attempts');
    }

    /**
     * Property 2: QR Token Collision Retry
     * Validates: Requirements 1.5
     *
     * Data provider test: verify that exactly 3 retries are attempted
     * before throwing RuntimeException, using multiple different colliding tokens.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('collidingTokenProvider')]
    public function test_retries_exactly_three_times_before_exception(string $collidingToken): void
    {
        // Pre-insert a revision with the colliding token
        DocumentRevision::withoutEvents(function () use ($collidingToken) {
            DocumentRevision::create([
                'document_id' => $this->document->id,
                'revision_number' => '00',
                'file_path' => 'documents/existing.pdf',
                'status' => 'Draft',
                'qr_token' => $collidingToken,
                'uploader_id' => 1,
            ]);
        });

        // Track how many times Str::random is called
        $callCount = 0;
        Str::createRandomStringsUsing(function () use ($collidingToken, &$callCount) {
            $callCount++;
            return $collidingToken;
        });

        try {
            DocumentRevision::create([
                'document_id' => $this->document->id,
                'revision_number' => '01',
                'file_path' => 'documents/new.pdf',
                'status' => 'Draft',
                'uploader_id' => 1,
            ]);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertEquals(3, $callCount, 'Should attempt exactly 3 times before failing');
            $this->assertStringContainsString('Failed to generate unique QR token', $e->getMessage());
        }
    }

    /**
     * Provides various colliding token values to test retry behavior
     * across different token patterns.
     */
    public static function collidingTokenProvider(): array
    {
        $tokens = [];
        // Generate 20 different 16-character tokens to test collision retry
        for ($i = 0; $i < 20; $i++) {
            $token = substr(str_repeat(str_pad((string) $i, 4, '0', STR_PAD_LEFT) . 'AbCdEfGh1234', 2), 0, 16);
            $tokens["colliding_token_{$i}"] = [$token];
        }
        return $tokens;
    }
}
