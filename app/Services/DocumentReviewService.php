<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentDestruction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DocumentReviewService
{
    /**
     * Hitung Baseline Date untuk dokumen.
     * Mengambil tanggal terbaru dari:
     * - revision_date revisi terakhir berstatus Published/Terbit
     * - review_date kaji ulang terakhir
     * - created_at dokumen (fallback)
     *
     * PENTING: Dokumen harus sudah di-eager-load dengan relasi 'revisions' dan 'reviews'
     * untuk menghindari N+1 query saat dipanggil dalam loop.
     */
    public function getBaselineDate(Document $document): Carbon
    {
        // Ambil revision_date terbaru dari revisi berstatus Published/Terbit
        $latestPublishedRevisionDate = $document->revisions
            ->whereIn('status', ['Published', 'Terbit'])
            ->max('revision_date');

        // Ambil review_date terbaru dari kaji ulang terakhir
        $latestReviewDate = $document->reviews->max('review_date');

        // Tentukan Baseline Date dari nilai terbaru kedua sumber
        $candidates = collect([
            $latestPublishedRevisionDate ? Carbon::parse($latestPublishedRevisionDate) : null,
            $latestReviewDate ? Carbon::parse($latestReviewDate) : null,
        ])->filter();

        if ($candidates->isEmpty()) {
            // Fallback ke created_at dokumen
            return Carbon::parse($document->created_at);
        }

        return $candidates->max();
    }

    /**
     * Hitung Due Date = Baseline Date + 365 hari.
     */
    public function getDueDate(Document $document): Carbon
    {
        return $this->getBaselineDate($document)->copy()->addDays(365);
    }

    /**
     * Hitung sisa hari menuju Due Date.
     * Negatif = overdue.
     */
    public function getDaysRemaining(Document $document): int
    {
        return (int) Carbon::today()->diffInDays($this->getDueDate($document), false);
    }

    /**
     * Ambil daftar dokumen yang mendekati due date (≤60 hari) atau overdue.
     * Hanya dokumen aktif (memiliki revisi Published/Terbit, tidak soft-deleted).
     * Menggunakan eager loading: Document::with(['revisions', 'reviews'])->get()
     * lalu filter di PHP berdasarkan computed due date.
     */
    public function getUpcomingDocuments(): Collection
    {
        return $this->getActiveDocuments()->filter(function (Document $document) {
            return $this->getDaysRemaining($document) <= 60;
        })->values();
    }

    /**
     * Ambil daftar dokumen overdue (sisa hari < 0).
     */
    public function getOverdueDocuments(): Collection
    {
        return $this->getActiveDocuments()->filter(function (Document $document) {
            return $this->getDaysRemaining($document) < 0;
        })->values();
    }

    /**
     * Ambil daftar dokumen dengan sisa waktu ≤60 hari (belum overdue).
     * 0 < days remaining ≤ 60
     */
    public function getDueSoonDocuments(): Collection
    {
        return $this->getActiveDocuments()->filter(function (Document $document) {
            $daysRemaining = $this->getDaysRemaining($document);

            return $daysRemaining > 0 && $daysRemaining <= 60;
        })->values();
    }

    /**
     * Ambil semua dokumen aktif dengan eager loading.
     * Dokumen aktif = memiliki setidaknya satu revisi berstatus Published/Terbit.
     */
    protected function getActiveDocuments(): Collection
    {
        return Document::with(['revisions', 'reviews'])
            ->whereHas('revisions', function ($query) {
                $query->whereIn('status', ['Published', 'Terbit']);
            })
            ->get();
    }

    /**
     * Buat draf pengajuan pemusnahan dokumen otomatis jika status evaluasi adalah 'Perlu dimusnahkan'
     * dan belum ada pengajuan pemusnahan yang berstatus Pending.
     */
    public function createDestructionRequestIfNeeded(
        Document $document,
        int $proposerId,
        ?string $reason = null,
        ?string $method = 'Penarikan Akses Digital'
    ): ?DocumentDestruction {
        $existing = DocumentDestruction::where('document_id', $document->id)
            ->where('status', 'Pending')
            ->first();

        if ($existing) {
            return $existing;
        }

        return DocumentDestruction::create([
            'document_id' => $document->id,
            'proposer_id' => $proposerId,
            'reason' => ! empty($reason) ? $reason : 'Hasil Kaji Ulang Dokumen: Perlu dimusnahkan',
            'method' => $method ?: 'Penarikan Akses Digital',
            'status' => 'Pending',
        ]);
    }
}
