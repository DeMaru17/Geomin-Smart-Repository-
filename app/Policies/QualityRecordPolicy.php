<?php

namespace App\Policies;

use App\Models\QualityRecord;
use App\Models\User;

class QualityRecordPolicy
{
    /**
     * Cek apakah user memiliki role admin atau manajemen.
     */
    private function isAdminOrManajemen(User $user): bool
    {
        return in_array($user->role, ['admin', 'manajemen']);
    }

    /**
     * Semua user terautentikasi bisa melihat daftar rekaman mutu.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Semua user terautentikasi bisa melihat detail rekaman mutu.
     */
    public function view(User $user, QualityRecord $qualityRecord): bool
    {
        return true;
    }

    /**
     * Semua user terautentikasi bisa membuat rekaman mutu.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Semua user terautentikasi bisa mengedit rekaman mutu.
     */
    public function update(User $user, QualityRecord $qualityRecord): bool
    {
        return true;
    }

    /**
     * Hard delete tidak diizinkan untuk siapapun (preservasi audit trail).
     */
    public function delete(User $user, QualityRecord $qualityRecord): bool
    {
        return false;
    }

    /**
     * Hanya admin/manajemen yang bisa menjalankan aksi pemusnahan (non-destruktif).
     */
    public function destroy(User $user, QualityRecord $qualityRecord): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    /**
     * Akses download file berdasarkan klasifikasi kerahasiaan:
     * - 'Sangat Rahasia': hanya admin/manajemen
     * - 'Rahasia' atau 'Biasa': semua user terautentikasi
     */
    public function downloadFile(User $user, QualityRecord $qualityRecord): bool
    {
        if ($qualityRecord->confidentiality === 'Sangat Rahasia') {
            return $this->isAdminOrManajemen($user);
        }

        return true;
    }

    /**
     * Akses preview file berdasarkan klasifikasi kerahasiaan:
     * - 'Sangat Rahasia': hanya admin/manajemen
     * - 'Rahasia' atau 'Biasa': semua user terautentikasi
     */
    public function previewFile(User $user, QualityRecord $qualityRecord): bool
    {
        if ($qualityRecord->confidentiality === 'Sangat Rahasia') {
            return $this->isAdminOrManajemen($user);
        }

        return true;
    }
}
