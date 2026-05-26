<?php

namespace App\Services;

use App\Models\ChangeRequest;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentDistributionLog;
use App\Models\DocumentRevision;
use App\Models\QualityRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class DashboardStatsService
{
    /**
     * Jumlah dokumen aktif (memiliki setidaknya satu revisi berstatus Published/Terbit).
     */
    public function getActiveDocumentCount(): int
    {
        return Document::whereHas('revisions', function ($query) {
            $query->whereIn('status', ['Published', 'Terbit']);
        })->count();
    }

    /**
     * Jumlah dokumen internal yang aktif (is_external = false).
     */
    public function getInternalDocumentCount(): int
    {
        return Document::where('is_external', false)
            ->whereHas('revisions', function ($query) {
                $query->whereIn('status', ['Published', 'Terbit']);
            })->count();
    }

    /**
     * Jumlah dokumen eksternal yang aktif (is_external = true).
     */
    public function getExternalDocumentCount(): int
    {
        return Document::where('is_external', true)
            ->whereHas('revisions', function ($query) {
                $query->whereIn('status', ['Published', 'Terbit']);
            })->count();
    }

    /**
     * Jumlah total revisi dengan status Published/Terbit.
     */
    public function getPublishedRevisionCount(): int
    {
        return DocumentRevision::whereIn('status', ['Published', 'Terbit'])->count();
    }

    /**
     * Jumlah rekaman mutu yang sudah kedaluwarsa (expiration_date < hari ini).
     */
    public function getExpiredQualityRecordCount(): int
    {
        return QualityRecord::whereNotNull('expiration_date')
            ->where('expiration_date', '<', Carbon::today())
            ->count();
    }

    /**
     * Jumlah rekaman mutu yang akan kedaluwarsa dalam $days hari ke depan.
     * (today <= expiration_date <= today + $days)
     */
    public function getNearExpirationQualityRecordCount(int $days = 30): int
    {
        $today = Carbon::today();

        return QualityRecord::whereNotNull('expiration_date')
            ->where('expiration_date', '>=', $today)
            ->where('expiration_date', '<=', $today->copy()->addDays($days))
            ->count();
    }

    /**
     * Cek apakah user memiliki role admin atau manajemen.
     * Jika $user null, gunakan user yang sedang login.
     */
    public function isAdminOrManajemen(?User $user = null): bool
    {
        $user = $user ?? Auth::user();

        if (! $user) {
            return false;
        }

        return in_array($user->role, ['admin', 'manajemen']);
    }

    /**
     * Jumlah pengajuan revisi dengan status "Pending".
     * Jika $userId diberikan, filter berdasarkan proposer_id.
     */
    public function getPendingChangeRequestCount(?int $userId = null): int
    {
        return $this->changeRequestQuery($userId)
            ->where('approval_status', 'Pending')
            ->count();
    }

    /**
     * Jumlah pengajuan revisi yang sedang ditinjau (status "Di uji coba" atau "Dibahas di RTM").
     * Jika $userId diberikan, filter berdasarkan proposer_id.
     */
    public function getUnderReviewChangeRequestCount(?int $userId = null): int
    {
        return $this->changeRequestQuery($userId)
            ->whereIn('approval_status', ['Di uji coba', 'Dibahas di RTM'])
            ->count();
    }

    /**
     * Jumlah pengajuan revisi yang disetujui (status "Segera dibuat revisi").
     * Jika $userId diberikan, filter berdasarkan proposer_id.
     */
    public function getApprovedChangeRequestCount(?int $userId = null): int
    {
        return $this->changeRequestQuery($userId)
            ->where('approval_status', 'Segera dibuat revisi')
            ->count();
    }

    /**
     * Daftar pengajuan revisi terbaru, diurutkan berdasarkan proposal_date descending.
     * Jika $userId diberikan, filter berdasarkan proposer_id.
     */
    public function getLatestChangeRequests(int $limit = 5, ?int $userId = null): Collection
    {
        return $this->changeRequestQuery($userId)
            ->with(['document', 'proposer'])
            ->orderByDesc('proposal_date')
            ->limit($limit)
            ->get();
    }

    /**
     * Base query builder untuk change requests dengan optional user filtering.
     * Jika $userId null, return semua records (admin/manajemen view).
     * Jika $userId diberikan, filter berdasarkan proposer_id (regular user view).
     */
    private function changeRequestQuery(?int $userId = null): Builder
    {
        $query = ChangeRequest::query();

        if ($userId !== null) {
            $query->where('proposer_id', $userId);
        }

        return $query;
    }

    /**
     * Daftar log distribusi terbaru, diurutkan berdasarkan accessed_at descending.
     * Eager load relasi documentRevision dan document induknya untuk keperluan tampilan.
     */
    public function getRecentDistributionLogs(int $limit = 10): Collection
    {
        return DocumentDistributionLog::with(['documentRevision.document'])
            ->orderByDesc('accessed_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Distribusi dokumen aktif per kategori.
     * Hanya kategori yang memiliki dokumen aktif yang disertakan.
     *
     * @return array<int, array{name: string, count: int}>
     */
    public function getDocumentsByCategory(): array
    {
        return DocumentCategory::whereHas('documents', function ($query) {
            $query->whereHas('revisions', function ($q) {
                $q->whereIn('status', ['Published', 'Terbit']);
            });
        })
            ->withCount(['documents' => function ($query) {
                $query->whereHas('revisions', function ($q) {
                    $q->whereIn('status', ['Published', 'Terbit']);
                });
            }])
            ->get()
            ->map(fn($category) => [
                'name' => $category->name,
                'count' => $category->documents_count,
            ])
            ->toArray();
    }

    /**
     * Distribusi dokumen aktif per departemen, diurutkan dari terbanyak ke tersedikit.
     * Hanya departemen yang memiliki dokumen aktif yang disertakan.
     *
     * @return array<int, array{name: string, count: int}>
     */
    public function getDocumentsByDepartment(): array
    {
        return Department::whereHas('documents', function ($query) {
            $query->whereHas('revisions', function ($q) {
                $q->whereIn('status', ['Published', 'Terbit']);
            });
        })
            ->withCount(['documents' => function ($query) {
                $query->whereHas('revisions', function ($q) {
                    $q->whereIn('status', ['Published', 'Terbit']);
                });
            }])
            ->orderByDesc('documents_count')
            ->get()
            ->map(fn($department) => [
                'name' => $department->name,
                'count' => $department->documents_count,
            ])
            ->toArray();
    }

    /**
     * Tren revisi bulanan untuk N bulan terakhir.
     * Menghitung revisi berstatus Published/Terbit per bulan, dengan zero-fill untuk bulan tanpa revisi.
     *
     * @return array<int, array{month: string, count: int}>
     */
    public function getMonthlyRevisionTrend(int $months = 12): array
    {
        $startDate = Carbon::today()->subMonths($months - 1)->startOfMonth();

        // Query revisi per bulan
        $revisions = DocumentRevision::whereIn('status', ['Published', 'Terbit'])
            ->where('revision_date', '>=', $startDate)
            ->selectRaw("DATE_FORMAT(revision_date, '%Y-%m') as month, COUNT(*) as count")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('count', 'month');

        // Zero-fill semua bulan
        $result = [];
        $current = $startDate->copy();
        $endMonth = Carbon::today()->startOfMonth();

        while ($current->lte($endMonth)) {
            $monthKey = $current->format('Y-m');
            $result[] = [
                'month' => $monthKey,
                'count' => (int) ($revisions[$monthKey] ?? 0),
            ];
            $current->addMonth();
        }

        return $result;
    }
}
