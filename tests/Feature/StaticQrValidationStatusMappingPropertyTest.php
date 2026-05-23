<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * @group Feature: dual-qr-pdf-stamping, Property 9: Static QR Validation Status Mapping
 */
#[Group('Feature: dual-qr-pdf-stamping, Property 9: Static QR Validation Status Mapping')]
class StaticQrValidationStatusMappingPropertyTest extends TestCase
{
    use DatabaseTransactions;

    protected array $connectionsToTransact = ['mysql', 'mysql_hris'];

    private Document $document;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $uniqueSuffix = uniqid();

        // Create a user for authentication
        $this->user = User::create([
            'name' => 'Test User ' . $uniqueSuffix,
            'email' => 'testuser_' . $uniqueSuffix . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        // Create prerequisite records for foreign keys
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
            'title' => 'Test Document ' . $uniqueSuffix,
            'category_id' => $category->id,
            'department_id' => $department->id,
            'is_external' => false,
            'retention_period_months' => 36,
        ]);
    }

    /**
     * Property 9: Static QR Validation Status Mapping
     * Validates: Requirements 9.7, 9.8, 9.9
     *
     * For any DocumentRevision matched by qr_token:
     * - If status is Published or Terbit, the validation page SHALL display status "Valid"
     * - If status is Obsolete, the validation page SHALL display status "Obsolete"
     * - If status is Draft, In_Review, or Approved, the validation page SHALL display "Belum Terbit"
     *
     * Note: The "Buka Dokumen" button is intentionally disabled and the route
     * does not require authentication (public access for QR validation).
     */
    #[DataProvider('statusMappingProvider')]
    public function test_status_maps_to_correct_label(string $status, string $expectedLabel): void
    {
        $token = 'TESTTOKEN' . substr(md5($status . uniqid()), 0, 8);

        DocumentRevision::withoutEvents(function () use ($status, $token) {
            DocumentRevision::create([
                'document_id' => $this->document->id,
                'revision_number' => '01',
                'file_path' => 'revisions/test.pdf',
                'status' => $status,
                'qr_token' => $token,
                'uploader_id' => $this->user->id,
            ]);
        });

        $response = $this->get('/validasi-cetak/' . $token);

        $response->assertStatus(200);
        $response->assertSee($expectedLabel);
    }

    /**
     * Property 9: Static QR Validation Status Mapping
     * Validates: Requirements 9.7, 9.8, 9.9
     *
     * Verify 404 for non-existent qr_token.
     */
    public function test_returns_404_for_nonexistent_qr_token(): void
    {
        $response = $this->get('/validasi-cetak/NONEXISTENTTOKEN');

        $response->assertStatus(404);
    }

    /**
     * Property 9: Static QR Validation Status Mapping
     * Validates: Requirements 9.7, 9.8, 9.9
     *
     * Generate revisions with all possible statuses using Faker-generated data
     * to verify correct status label mapping across 100+ iterations.
     *
     * Note: The "Buka Dokumen" button is intentionally disabled and the route
     * does not require authentication (public access for QR validation).
     */
    #[DataProvider('randomStatusMappingProvider')]
    public function test_random_revisions_map_to_correct_status_label(
        string $status,
        string $revisionNumber,
        string $expectedLabel,
        int $iteration
    ): void {
        $token = 'RND' . str_pad((string) $iteration, 5, '0', STR_PAD_LEFT) . substr(md5((string) $iteration), 0, 8);

        DocumentRevision::withoutEvents(function () use ($status, $token, $revisionNumber) {
            DocumentRevision::create([
                'document_id' => $this->document->id,
                'revision_number' => $revisionNumber,
                'file_path' => 'revisions/test.pdf',
                'status' => $status,
                'qr_token' => $token,
                'uploader_id' => $this->user->id,
            ]);
        });

        $response = $this->get('/validasi-cetak/' . $token);

        $response->assertStatus(200);
        $response->assertSee($expectedLabel);
    }

    /**
     * Data provider with all possible status combinations.
     * Each status maps to its expected display label.
     */
    public static function statusMappingProvider(): iterable
    {
        // Published → Valid
        yield 'Published status shows Valid' => ['Published', 'Valid'];

        // Terbit → Valid
        yield 'Terbit status shows Valid' => ['Terbit', 'Valid'];

        // Obsolete → Obsolete
        yield 'Obsolete status shows Obsolete' => ['Obsolete', 'Obsolete'];

        // Draft → Belum Terbit
        yield 'Draft status shows Belum Terbit' => ['Draft', 'Belum Terbit'];

        // In_Review → Belum Terbit
        yield 'In_Review status shows Belum Terbit' => ['In_Review', 'Belum Terbit'];

        // Approved → Belum Terbit
        yield 'Approved status shows Belum Terbit' => ['Approved', 'Belum Terbit'];
    }

    /**
     * Generate 100+ random revision payloads with all possible statuses.
     * Verifies the status mapping property holds across many iterations.
     */
    public static function randomStatusMappingProvider(): iterable
    {
        $faker = \Faker\Factory::create();
        $statuses = ['Published', 'Terbit', 'Obsolete', 'Draft', 'In_Review', 'Approved'];

        $statusToLabel = [
            'Published' => 'Valid',
            'Terbit' => 'Valid',
            'Obsolete' => 'Obsolete',
            'Draft' => 'Belum Terbit',
            'In_Review' => 'Belum Terbit',
            'Approved' => 'Belum Terbit',
        ];

        for ($i = 0; $i < 110; $i++) {
            $status = $faker->randomElement($statuses);
            $revisionNumber = $faker->numerify('0#');

            yield "random_status_iteration_{$i}_{$status}" => [
                $status,
                $revisionNumber,
                $statusToLabel[$status],
                $i,
            ];
        }
    }
}
