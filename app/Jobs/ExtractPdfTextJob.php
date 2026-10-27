<?php

namespace App\Jobs;

use App\Models\DocumentRevision;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\PdfToText\Pdf;

class ExtractPdfTextJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120; // Memberi waktu 2 menit jika PDF sangat tebal

    protected int|string $revisionId;

    public function __construct(int|string $revisionId)
    {
        $this->revisionId = $revisionId;
        $this->afterCommit = true;
    }

    public function handle(): void
    {
        $revision = DocumentRevision::query()->find($this->revisionId);

        // Pastikan revisi dan fail PDF-nya ada
        if (! $revision || ! $revision->file_path) {
            return;
        }

        $absolutePath = Storage::path($revision->file_path);

        try {
            // Path ke pdftotext (fleksibel via env: Windows lokal / Linux production)
            $binary = $this->resolvePdfToTextBinary();
            $pdf = $binary ? new Pdf($binary) : new Pdf();
            $text = $pdf->setPdf($absolutePath)->text();

            // 0. Pastikan teks valid UTF-8 (pdftotext bisa menghasilkan byte non-UTF-8)
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
            $text = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text);

            // 1. Ubah spasi/tab yang berlebih menjadi 1 spasi biasa
            $cleanText = preg_replace('/[ \t]+/', ' ', $text);
            // 2. Hapus jarak enter yang terlalu banyak (maksimal 2 enter berurutan)
            $cleanText = preg_replace("/[\r\n]{3,}/", "\n\n", $cleanText);
            // 3. Bersihkan spasi di awal dan akhir dokumen
            $cleanText = trim($cleanText);

            // Simpan ke database
            $revision->update([
                'extracted_text' => $cleanText,
            ]);
        } catch (\Exception $e) {
            // Jika gagal, catat di log Laravel agar bisa diinvestigasi
            Log::error("Gagal mengekstrak teks PDF untuk Revisi ID {$this->revisionId}: " . $e->getMessage());
        }
    }

    /**
     * Menentukan path binary pdftotext yang valid pada sistem (Windows Laragon/Poppler/Xpdf atau Linux/macOS).
     */
    protected function resolvePdfToTextBinary(): ?string
    {
        // 1. Cek dari environment variable jika disetel secara eksplisit
        $envPath = env('PDFTOTEXT_PATH');
        if (! empty($envPath) && (file_exists($envPath) || is_executable($envPath))) {
            return $envPath;
        }

        // 2. Deteksi lokasi umum binary pdftotext di Windows & Laragon
        $candidates = [
            'C:\\laragon\\bin\\git\\mingw64\\bin\\pdftotext.exe',
            'C:\\laragon\\bin\\git\\usr\\bin\\pdftotext.exe',
            'C:\\Program Files\\xpdf-tools-win\\bin64\\pdftotext.exe',
            'C:\\Program Files\\poppler\\bin\\pdftotext.exe',
            '/usr/bin/pdftotext',
            '/usr/local/bin/pdftotext',
            '/opt/homebrew/bin/pdftotext',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        // 3. Fallback: null agar Spatie\PdfToText\Pdf mencoba mencari sendiri di PATH
        return null;
    }
}
