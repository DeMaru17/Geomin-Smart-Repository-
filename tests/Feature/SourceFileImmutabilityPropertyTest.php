<?php

namespace Tests\Feature;

use App\Models\DocumentRevision;
use App\Services\PdfStamperService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use FPDF;
use Tests\TestCase;

/**
 * @group Feature: dual-qr-pdf-stamping, Property 3: Source File Immutability
 */
#[Group('Feature: dual-qr-pdf-stamping, Property 3: Source File Immutability')]
class SourceFileImmutabilityPropertyTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Validates: Requirements 3.1, 4.1
     *
     * Property 3: Source File Immutability
     * For any PDF stamping operation (controlled or uncontrolled), the original PDF file
     * referenced by file_path on disk SHALL remain byte-identical before and after the
     * operation completes.
     */
    #[DataProvider('samplePdfSizesProvider')]
    public function test_source_file_unchanged_after_stamp_controlled(int $pageCount): void
    {
        $this->fakeQrCodeFacade();

        $pdfPath = $this->createSamplePdf($pageCount);
        $relativePath = 'test-pdfs/' . basename($pdfPath);

        $revision = DocumentRevision::factory()->create([
            'file_path' => $relativePath,
            'qr_token' => 'TestToken1234567',
        ]);

        $hashBefore = hash_file('sha256', $pdfPath);

        $service = new PdfStamperService();
        $service->stampControlled($revision);

        $hashAfter = hash_file('sha256', $pdfPath);

        $this->assertSame(
            $hashBefore,
            $hashAfter,
            "Source PDF ({$pageCount} pages) was modified by stampControlled()"
        );

        @unlink($pdfPath);
    }

    /**
     * Validates: Requirements 3.1, 4.1
     *
     * Property 3: Source File Immutability
     * For any PDF stamping operation (controlled or uncontrolled), the original PDF file
     * referenced by file_path on disk SHALL remain byte-identical before and after the
     * operation completes.
     */
    #[DataProvider('samplePdfSizesProvider')]
    public function test_source_file_unchanged_after_stamp_uncontrolled(int $pageCount): void
    {
        $this->fakeQrCodeFacade();

        $pdfPath = $this->createSamplePdf($pageCount);
        $relativePath = 'test-pdfs/' . basename($pdfPath);

        $revision = DocumentRevision::factory()->create([
            'file_path' => $relativePath,
            'qr_token' => 'TestToken7654321',
        ]);

        $hashBefore = hash_file('sha256', $pdfPath);

        $service = new PdfStamperService();
        $service->stampUncontrolled($revision);

        $hashAfter = hash_file('sha256', $pdfPath);

        $this->assertSame(
            $hashBefore,
            $hashAfter,
            "Source PDF ({$pageCount} pages) was modified by stampUncontrolled()"
        );

        @unlink($pdfPath);
    }

    /**
     * Provide varying PDF page counts to test with different file sizes.
     */
    public static function samplePdfSizesProvider(): iterable
    {
        yield '1 page PDF' => [1];
        yield '3 page PDF' => [3];
        yield '10 page PDF' => [10];
    }

    /**
     * Replace the QrCode facade with a fake that generates a valid PNG
     * without requiring the imagick extension. This isolates the property
     * under test (source file immutability) from the QR rendering dependency.
     */
    private function fakeQrCodeFacade(): void
    {
        $fake = new class {
            public function format($format)
            {
                return $this;
            }
            public function size($size)
            {
                return $this;
            }
            public function generate($content, $path = null)
            {
                if ($path) {
                    $img = imagecreatetruecolor(10, 10);
                    imagepng($img, $path);
                    imagedestroy($img);
                    return null;
                }
                return 'fake-qr-content';
            }
        };

        QrCode::swap($fake);
    }

    /**
     * Create a sample PDF file in storage/app/test-pdfs/ with the given number of pages.
     */
    private function createSamplePdf(int $pageCount): string
    {
        $dir = storage_path('app/private/test-pdfs');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'test_' . uniqid() . '.pdf';
        $filePath = $dir . DIRECTORY_SEPARATOR . $filename;

        $pdf = new FPDF();
        for ($i = 1; $i <= $pageCount; $i++) {
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(0, 10, "Test Page {$i} of {$pageCount}", 0, 1, 'C');
            $pdf->SetFont('Arial', '', 12);
            $pdf->MultiCell(0, 10, "This is sample content for property testing. Page {$i}.");
        }
        $pdf->Output('F', $filePath);

        return $filePath;
    }
}
