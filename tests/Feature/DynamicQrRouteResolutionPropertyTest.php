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
 * @group Feature: dual-qr-pdf-stamping, Property 8: Dynamic QR Route Resolution
 */
#[Group('Feature: dual-qr-pdf-stamping, Property 8: Dynamic QR Route Resolution')]
class DynamicQrRouteResolutionPropertyTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['mysql', 'mysql_hris'];

    private Document $document;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $uniqueSuffix = uniqid();

        $department = Department::create([
            'code' => 'DQR' . $uniqueSuffix,
            'name' => 'Dynamic QR Test Dept ' . $uniqueSuffix,
        ]);

        $category = DocumentCategory::create([
            'code' => 'DQR' . $uniqueSuffix,
            'name' => 'Dynamic QR Test Cat ' . $uniqueSuffix,
        ]);

        $this->document = Document::create([
            'document_number' => 'DQR-' . $uniqueSuffix,
            'title' => 'Dynamic QR Test Document',
            'category_id' => $category->id,
            'department_id' => $department->id,
            'is_external' => false,
            'retention_period_months' => 36,
        ]);

        $this->user = User::factory()->create([
            'role' => 'admin',
        ]);
    }

    /**
     * Property 8: Dynamic QR Route Resolution
     * Validates: Requirements 8.2, 8.3, 8.4
     *
     * For any document with one or more revisions having status Published,
     * the dynamic QR route SHALL redirect (HTTP 302) to the secure viewer URL
     * for the revision with the highest id among those published revisions.
     */
    #[DataProvider('multiplePublishedRevisionsProvider')]
    public function test_redirects_to_highest_id_published_revision(array $revisionStatuses): void
    {
        $revisions = [];

        // Create revisions without events to avoid the auto-obsolete behavior
        foreach ($revisionStatuses as $index => $status) {
            $revision = DocumentRevision::withoutEvents(function () use ($index, $status) {
                return DocumentRevision::create([
                    'document_id' => $this->document->id,
                    'revision_number' => str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                    'file_path' => 'revisions/test-' . uniqid() . '.pdf',
                    'status' => $status,
                    'qr_token' => 'DQR' . str_pad((string) $index, 13, uniqid(), STR_PAD_LEFT),
                    'uploader_id' => $this->user->id,
                ]);
            });
            $revisions[] = $revision;
        }

        // Find the expected revision: highest id among Published
        $expectedRevision = collect($revisions)
            ->filter(fn($r) => $r->status === 'Published')
            ->sortByDesc('id')
            ->first();

        // This test only runs when there IS at least one Published revision
        if ($expectedRevision === null) {
            $this->markTestSkipped('No Published revision in this scenario — covered by no_published_revision test.');
        }

        $response = $this->actingAs($this->user)
            ->get('/dokumen/aktif/' . $this->document->document_number);

        $response->assertStatus(302);
        $response->assertRedirect(route('secure.viewer', ['id' => $expectedRevision->id]));
    }

    /**
     * Property 8: Dynamic QR Route Resolution
     * Validates: Requirements 8.2, 8.3, 8.4
     *
     * When no Published revision exists for the document, the route SHALL
     * render the no-published-revision view instead of redirecting.
     */
    #[DataProvider('noPublishedRevisionsProvider')]
    public function test_shows_no_published_revision_view_when_none_published(array $revisionStatuses): void
    {
        foreach ($revisionStatuses as $index => $status) {
            DocumentRevision::withoutEvents(function () use ($index, $status) {
                return DocumentRevision::create([
                    'document_id' => $this->document->id,
                    'revision_number' => str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                    'file_path' => 'revisions/test-' . uniqid() . '.pdf',
                    'status' => $status,
                    'qr_token' => 'NPR' . str_pad((string) $index, 13, uniqid(), STR_PAD_LEFT),
                    'uploader_id' => $this->user->id,
                ]);
            });
        }

        $response = $this->actingAs($this->user)
            ->get('/dokumen/aktif/' . $this->document->document_number);

        $response->assertStatus(200);
        $response->assertViewIs('no-published-revision');
    }

    /**
     * Property 8: Dynamic QR Route Resolution
     * Validates: Requirements 8.2, 8.3, 8.4
     *
     * When the document_number does not match any document, the route SHALL return 404.
     */
    public function test_returns_404_for_nonexistent_document(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/dokumen/aktif/NONEXISTENT-DOC-' . uniqid());

        $response->assertStatus(404);
    }

    /**
     * Data provider: generates 100+ scenarios with multiple revisions
     * in various statuses where at least one is Published.
     * Verifies the route always picks the highest-id Published revision.
     */
    public static function multiplePublishedRevisionsProvider(): iterable
    {
        $faker = \Faker\Factory::create();
        $allStatuses = ['Draft', 'In_Review', 'Approved', 'Published', 'Obsolete'];

        for ($i = 0; $i < 100; $i++) {
            $revisionCount = $faker->numberBetween(2, 6);
            $statuses = [];

            // Ensure at least one Published revision
            $publishedIndex = $faker->numberBetween(0, $revisionCount - 1);

            for ($j = 0; $j < $revisionCount; $j++) {
                if ($j === $publishedIndex) {
                    $statuses[] = 'Published';
                } else {
                    $statuses[] = $faker->randomElement($allStatuses);
                }
            }

            yield "scenario_{$i}_revisions_" . implode('_', $statuses) => [
                $statuses,
            ];
        }
    }

    /**
     * Data provider: generates 20 scenarios where NO revision has Published status.
     * Covers combinations of Draft, In_Review, Approved, and Obsolete.
     */
    public static function noPublishedRevisionsProvider(): iterable
    {
        $faker = \Faker\Factory::create();
        $nonPublishedStatuses = ['Draft', 'In_Review', 'Approved', 'Obsolete'];

        for ($i = 0; $i < 20; $i++) {
            $revisionCount = $faker->numberBetween(1, 5);
            $statuses = [];

            for ($j = 0; $j < $revisionCount; $j++) {
                $statuses[] = $faker->randomElement($nonPublishedStatuses);
            }

            yield "no_published_{$i}_" . implode('_', $statuses) => [
                $statuses,
            ];
        }
    }
}
