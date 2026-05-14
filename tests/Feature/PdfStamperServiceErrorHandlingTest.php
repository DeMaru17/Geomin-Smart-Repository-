<?php

namespace Tests\Feature;

use App\Models\DocumentRevision;
use App\Services\PdfStamperService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

/**
 * Unit tests for PdfStamperService error handling.
 *
 * Validates: Requirements 3.7, 3.8, 4.6
 */
class PdfStamperServiceErrorHandlingTest extends TestCase
{
    use DatabaseTransactions;

    private PdfStamperService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PdfStamperService();
    }

    /**
     * Test that stampControlled throws RuntimeException when file_path is empty.
     *
     * Validates: Requirement 3.7
     */
    public function test_stamp_controlled_throws_when_file_path_is_empty(): void
    {
        $revision = DocumentRevision::factory()->create([
            'file_path' => '',
            'qr_token' => 'ErrorTest1234567',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Source PDF file not found');

        $this->service->stampControlled($revision);
    }

    /**
     * Test that stampUncontrolled throws RuntimeException when file_path is empty.
     *
     * Validates: Requirement 4.6
     */
    public function test_stamp_uncontrolled_throws_when_file_path_is_empty(): void
    {
        $revision = DocumentRevision::factory()->create([
            'file_path' => '',
            'qr_token' => 'ErrorTest2345678',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Source PDF file not found');

        $this->service->stampUncontrolled($revision);
    }

    /**
     * Test that stampControlled throws RuntimeException when file does not exist on disk.
     *
     * Validates: Requirement 3.7
     */
    public function test_stamp_controlled_throws_when_file_does_not_exist(): void
    {
        $revision = DocumentRevision::factory()->create([
            'file_path' => 'revisions/nonexistent-file-abc123.pdf',
            'qr_token' => 'ErrorTest3456789',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Source PDF file not found');

        $this->service->stampControlled($revision);
    }

    /**
     * Test that stampUncontrolled throws RuntimeException when file does not exist on disk.
     *
     * Validates: Requirement 4.6
     */
    public function test_stamp_uncontrolled_throws_when_file_does_not_exist(): void
    {
        $revision = DocumentRevision::factory()->create([
            'file_path' => 'revisions/nonexistent-file-xyz789.pdf',
            'qr_token' => 'ErrorTest4567890',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Source PDF file not found');

        $this->service->stampUncontrolled($revision);
    }

    /**
     * Test that stampControlled throws RuntimeException when stamp image is missing.
     *
     * Validates: Requirement 3.8
     */
    public function test_stamp_controlled_throws_when_stamp_image_missing(): void
    {
        // Create a valid PDF file so we pass the source file check
        $pdfPath = $this->createValidPdf();
        $relativePath = 'test-pdfs/' . basename($pdfPath);

        $revision = DocumentRevision::factory()->create([
            'file_path' => $relativePath,
            'qr_token' => 'ErrorTest5678901',
        ]);

        // Temporarily rename the controlled stamp to simulate missing asset
        $stampPath = public_path('stamps/controlled.png');
        $backupPath = public_path('stamps/controlled.png.bak');

        rename($stampPath, $backupPath);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Stamp asset not found: controlled');

            $this->service->stampControlled($revision);
        } finally {
            // Restore the stamp file
            if (file_exists($backupPath)) {
                rename($backupPath, $stampPath);
            }
            @unlink($pdfPath);
        }
    }

    /**
     * Test that stampUncontrolled throws RuntimeException when stamp image is missing.
     *
     * Validates: Requirement 3.8
     */
    public function test_stamp_uncontrolled_throws_when_stamp_image_missing(): void
    {
        // Create a valid PDF file so we pass the source file check
        $pdfPath = $this->createValidPdf();
        $relativePath = 'test-pdfs/' . basename($pdfPath);

        $revision = DocumentRevision::factory()->create([
            'file_path' => $relativePath,
            'qr_token' => 'ErrorTest6789012',
        ]);

        // Temporarily rename the uncontrolled stamp to simulate missing asset
        $stampPath = public_path('stamps/uncontrolled.png');
        $backupPath = public_path('stamps/uncontrolled.png.bak');

        rename($stampPath, $backupPath);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Stamp asset not found: uncontrolled');

            $this->service->stampUncontrolled($revision);
        } finally {
            // Restore the stamp file
            if (file_exists($backupPath)) {
                rename($backupPath, $stampPath);
            }
            @unlink($pdfPath);
        }
    }

    /**
     * Test that stampControlled throws RuntimeException when source file is not a valid PDF.
     *
     * Validates: Requirement 3.7
     */
    public function test_stamp_controlled_throws_when_file_is_invalid_pdf(): void
    {
        // Create a text file with .pdf extension (invalid PDF)
        $invalidPdfPath = $this->createInvalidPdf();
        $relativePath = 'test-pdfs/' . basename($invalidPdfPath);

        $revision = DocumentRevision::factory()->create([
            'file_path' => $relativePath,
            'qr_token' => 'ErrorTest7890123',
        ]);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/Failed to process PDF:/');

            $this->service->stampControlled($revision);
        } finally {
            @unlink($invalidPdfPath);
        }
    }

    /**
     * Test that stampUncontrolled throws RuntimeException when source file is not a valid PDF.
     *
     * Validates: Requirement 4.6
     */
    public function test_stamp_uncontrolled_throws_when_file_is_invalid_pdf(): void
    {
        // Create a text file with .pdf extension (invalid PDF)
        $invalidPdfPath = $this->createInvalidPdf();
        $relativePath = 'test-pdfs/' . basename($invalidPdfPath);

        $revision = DocumentRevision::factory()->create([
            'file_path' => $relativePath,
            'qr_token' => 'ErrorTest8901234',
        ]);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/Failed to process PDF:/');

            $this->service->stampUncontrolled($revision);
        } finally {
            @unlink($invalidPdfPath);
        }
    }

    /**
     * Create a valid sample PDF file in storage/app/private/test-pdfs/.
     */
    private function createValidPdf(): string
    {
        $dir = storage_path('app/private/test-pdfs');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'valid_' . uniqid() . '.pdf';
        $filePath = $dir . DIRECTORY_SEPARATOR . $filename;

        $pdf = new \FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'Test PDF for error handling', 0, 1, 'C');
        $pdf->Output('F', $filePath);

        return $filePath;
    }

    /**
     * Create an invalid PDF file (text file with .pdf extension) in storage/app/private/test-pdfs/.
     */
    private function createInvalidPdf(): string
    {
        $dir = storage_path('app/private/test-pdfs');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'invalid_' . uniqid() . '.pdf';
        $filePath = $dir . DIRECTORY_SEPARATOR . $filename;

        file_put_contents($filePath, 'This is not a valid PDF file. Just plain text content.');

        return $filePath;
    }
}
