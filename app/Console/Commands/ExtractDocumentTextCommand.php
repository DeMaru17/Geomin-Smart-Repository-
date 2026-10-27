<?php

namespace App\Console\Commands;

use App\Jobs\ExtractPdfTextJob;
use App\Models\DocumentRevision;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ExtractDocumentTextCommand extends Command
{
    /**
     * Nama dan tanda tangan perintah console.
     *
     * @var string
     */
    protected $signature = 'documents:extract-text 
                            {--id= : ID revisi spesifik yang ingin diekstrak}
                            {--force : Paksa ekstrak ulang berkas yang sudah memiliki extracted_text}';

    /**
     * Deskripsi perintah console.
     *
     * @var string
     */
    protected $description = 'Mengekstrak teks dari berkas PDF dokumen revisi ke database';

    /**
     * Eksekusi perintah console.
     */
    public function handle(): int
    {
        $specificId = $this->option('id');
        $force = (bool) $this->option('force');

        if ($specificId) {
            $revision = DocumentRevision::find($specificId);

            if (! $revision) {
                $this->error("Dokumen revisi dengan ID [{$specificId}] tidak ditemukan.");

                return self::FAILURE;
            }

            if (! $revision->file_path || ! str_ends_with(strtolower($revision->file_path), '.pdf')) {
                $this->error("Revisi [{$specificId}] tidak memiliki berkas PDF pada slot file_path.");

                return self::FAILURE;
            }

            if (! Storage::disk('local')->exists($revision->file_path)) {
                $this->error("Berkas fisik tidak ditemukan di storage: [{$revision->file_path}]");

                return self::FAILURE;
            }

            $this->info("Memproses ekstraksi teks untuk Revisi ID {$revision->id}...");
            ExtractPdfTextJob::dispatchSync($revision->id);
            $revision->refresh();

            $length = strlen($revision->extracted_text ?? '');
            $this->info("Berhasil diekstrak ({$length} bytes teks tersimpan).");

            return self::SUCCESS;
        }

        // Ambil seluruh revisi yang memiliki berkas PDF
        $query = DocumentRevision::query()
            ->whereNotNull('file_path')
            ->where('file_path', 'like', '%.pdf');

        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('extracted_text')->orWhere('extracted_text', '');
            });
        }

        $revisions = $query->get();

        if ($revisions->isEmpty()) {
            $this->info('Tidak ditemukan berkas PDF yang membutuhkan ekstraksi teks.');

            return self::SUCCESS;
        }

        $this->info("Menemukan {$revisions->count()} revisi untuk diproses ekstraksi teks.");
        $bar = $this->output->createProgressBar($revisions->count());
        $bar->start();

        $success = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($revisions as $rev) {
            if (! Storage::disk('local')->exists($rev->file_path)) {
                $skipped++;
                $bar->advance();
                continue;
            }

            try {
                ExtractPdfTextJob::dispatchSync($rev->id);
                $success++;
            } catch (\Throwable $e) {
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Selesai: {$success} berhasil diekstrak, {$skipped} dilewati (berkas fisik belum ada di storage), {$failed} gagal.");

        return self::SUCCESS;
    }
}
