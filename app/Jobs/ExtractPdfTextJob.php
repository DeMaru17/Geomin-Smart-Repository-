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
            // Path ke pdftotext.exe dari instalasi Poppler
            $text = (new Pdf('C:\\poppler-26.02.0\\Library\\bin\\pdftotext.exe'))
                ->setPdf($absolutePath)
                ->text();

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
}
