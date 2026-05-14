<?php

namespace App\Services;

use App\Models\DocumentRevision;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use setasign\Fpdi\Fpdi;

class PdfStamperService
{
    const QR_SIZE_MM = 20;          // QR code dimensions on validation page
    const STAMP_WIDTH_MM = 40;      // Stamp width in mm (small, non-intrusive)

    /**
     * Generate a controlled copy PDF:
     * - Stamp "Document Controlled" on page 2 (bottom center, small)
     * - QR validation code on a new last page
     */
    public function stampControlled(DocumentRevision $revision): string
    {
        $sourcePath = $this->resolveSourcePath($revision);
        $stampPath = $this->getStampPath('controlled');

        try {
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($sourcePath);
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to process PDF: ' . $e->getMessage());
        }

        // Generate QR code image
        $qrUrl = config('app.url') . '/validasi-cetak/' . $revision->qr_token;
        $qrImagePath = $this->generateQrImage($qrUrl);

        try {
            // Import all original pages
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getImportedPageSize($templateId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useImportedPage($templateId);

                // Overlay controlled stamp on page 2 only (bottom center, small)
                if ($pageNo === 2) {
                    $this->overlayStampBottomCenter($pdf, $stampPath, $size['width'], $size['height']);
                }
            }

            // Add a new last page with QR validation
            $this->addQrValidationPage($pdf, $qrImagePath, $revision);

            $output = $pdf->Output('S');
        } finally {
            if (file_exists($qrImagePath)) {
                @unlink($qrImagePath);
            }
        }

        return $output;
    }

    /**
     * Generate an uncontrolled copy PDF:
     * - Stamp "Uncontrolled" on page 2 only (bottom center, small)
     */
    public function stampUncontrolled(DocumentRevision $revision): string
    {
        $sourcePath = $this->resolveSourcePath($revision);
        $stampPath = $this->getStampPath('uncontrolled');

        try {
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($sourcePath);
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to process PDF: ' . $e->getMessage());
        }

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getImportedPageSize($templateId);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useImportedPage($templateId);

            // Overlay uncontrolled stamp on page 2 only (bottom center, small)
            if ($pageNo === 2) {
                $this->overlayStampBottomCenter($pdf, $stampPath, $size['width'], $size['height']);
            }
        }

        return $pdf->Output('S');
    }

    /**
     * Resolve the absolute file path for the source PDF.
     */
    private function resolveSourcePath(DocumentRevision $revision): string
    {
        if (empty($revision->file_path)) {
            throw new RuntimeException('Source PDF file not found');
        }

        $path = storage_path('app/private/' . $revision->file_path);

        if (!file_exists($path)) {
            throw new RuntimeException('Source PDF file not found');
        }

        return $path;
    }

    /**
     * Get stamp image path (controlled or uncontrolled).
     */
    private function getStampPath(string $type): string
    {
        $path = public_path("stamps/{$type}.png");

        if (!file_exists($path)) {
            throw new RuntimeException("Stamp asset not found: {$type}");
        }

        return $path;
    }

    /**
     * Overlay stamp image at bottom center of the page (small size).
     * Positioned above the footer area, centered horizontally.
     */
    private function overlayStampBottomCenter(Fpdi $pdf, string $stampPath, float $pageWidth, float $pageHeight): void
    {
        $imageInfo = getimagesize($stampPath);
        if ($imageInfo === false) {
            return;
        }

        $imgWidth = $imageInfo[0];
        $imgHeight = $imageInfo[1];
        $aspectRatio = $imgWidth / $imgHeight;

        // Use fixed small width (40mm) for the stamp
        $stampWidth = $pageWidth * 0.3;
        $stampHeight = $stampWidth / $aspectRatio;

        // Position: bottom center, 30mm from bottom edge
        $x = ($pageWidth - $stampWidth) / 2;
        $y = ($pageHeight - $stampHeight) / 2;

        $pdf->Image($stampPath, $x, $y, $stampWidth, $stampHeight, 'PNG');
    }

    /**
     * Add a new page at the end with QR validation code and document info.
     */
    private function addQrValidationPage(Fpdi $pdf, string $qrImagePath, DocumentRevision $revision): void
    {
        // Add A4 portrait page
        $pdf->AddPage('P', [210, 297]);

        // Title
        $pdf->SetFont('Helvetica', 'B', 14);
        $pdf->SetXY(0, 40);
        $pdf->Cell(210, 10, 'HALAMAN VALIDASI DOKUMEN', 0, 1, 'C');

        // Subtitle
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetXY(0, 55);
        $pdf->Cell(210, 6, 'Dokumen ini adalah salinan terkendali yang sah.', 0, 1, 'C');
        $pdf->Cell(210, 6, 'Scan QR code di bawah untuk memverifikasi keaslian dokumen.', 0, 1, 'C');

        // QR Code centered
        $qrSize = 50; // 50mm QR on validation page
        $qrX = (210 - $qrSize) / 2;
        $qrY = 80;

        if (file_exists($qrImagePath) && filesize($qrImagePath) > 0) {
            $pdf->Image($qrImagePath, $qrX, $qrY, $qrSize, $qrSize, 'PNG');
        }

        // Document info below QR
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetXY(30, 140);

        $document = $revision->document;
        $info = [
            'Nomor Dokumen' => $document->document_number ?? '-',
            'Judul' => $document->title ?? '-',
            'Revisi' => $revision->revision_number ?? '-',
            'Token Validasi' => $revision->qr_token ?? '-',
        ];

        foreach ($info as $label => $value) {
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->Cell(40, 7, $label . ':', 0, 0);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->Cell(100, 7, $value, 0, 1);
        }

        // Footer note
        $pdf->SetFont('Helvetica', 'I', 8);
        $pdf->SetXY(0, 250);
        $pdf->Cell(210, 5, 'Halaman ini digenerate secara otomatis oleh sistem GSR (Geomin Smart Repository).', 0, 1, 'C');
        $pdf->Cell(210, 5, 'Dokumen tanpa halaman validasi ini dianggap tidak sah.', 0, 1, 'C');
    }

    /**
     * Generate a QR code image as a temporary PNG file.
     * Uses BaconQrCode's encoder directly with GD rendering (no imagick needed).
     */
    private function generateQrImage(string $content): string
    {
        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qr_' . uniqid() . '.png';
        $size = 300;
        $margin = 1; // 1 module quiet zone

        try {
            $ecl = \BaconQrCode\Common\ErrorCorrectionLevel::M();
            $qrCode = \BaconQrCode\Encoder\Encoder::encode($content, $ecl);
            $matrix = $qrCode->getMatrix();
            $matrixWidth = $matrix->getWidth();
            $matrixHeight = $matrix->getHeight();

            $totalModules = $matrixWidth + ($margin * 2);
            $moduleSize = $size / $totalModules;

            $image = imagecreatetruecolor($size, $size);
            $white = imagecolorallocate($image, 255, 255, 255);
            $black = imagecolorallocate($image, 0, 0, 0);
            imagefill($image, 0, 0, $white);

            for ($y = 0; $y < $matrixHeight; $y++) {
                for ($x = 0; $x < $matrixWidth; $x++) {
                    if ($matrix->get($x, $y) === 1) {
                        $px = (int) round(($x + $margin) * $moduleSize);
                        $py = (int) round(($y + $margin) * $moduleSize);
                        $px2 = (int) round(($x + $margin + 1) * $moduleSize) - 1;
                        $py2 = (int) round(($y + $margin + 1) * $moduleSize) - 1;
                        imagefilledrectangle($image, $px, $py, $px2, $py2, $black);
                    }
                }
            }

            imagepng($image, $tempPath);
            imagedestroy($image);
        } catch (\Exception $e) {
            Log::error('QR code generation failed: ' . $e->getMessage());
            $this->createPlaceholderPng($tempPath, $size);
        }

        return $tempPath;
    }

    /**
     * Create a minimal placeholder PNG (used when QR generation fails).
     */
    private function createPlaceholderPng(string $outputPath, int $size): void
    {
        $image = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);

        // Draw a border
        imagerectangle($image, 0, 0, $size - 1, $size - 1, $black);

        // Draw "QR" text centered
        $fontSize = 5;
        $text = 'QR ERROR';
        $textWidth = imagefontwidth($fontSize) * strlen($text);
        $textHeight = imagefontheight($fontSize);
        $x = (int) (($size - $textWidth) / 2);
        $y = (int) (($size - $textHeight) / 2);
        imagestring($image, $fontSize, $x, $y, $text, $black);

        imagepng($image, $outputPath);
        imagedestroy($image);
    }
}
