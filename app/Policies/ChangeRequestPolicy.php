<?php

namespace App\Policies;

use App\Models\ChangeRequest;
use App\Models\User;

class ChangeRequestPolicy
{
    /**
     * Semua user terautentikasi bisa melihat daftar pengajuan revisi.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Semua user terautentikasi bisa melihat detail pengajuan revisi.
     */
    public function view(User $user, ChangeRequest $changeRequest): bool
    {
        return true;
    }

    /**
     * Semua user terautentikasi bisa membuat pengajuan revisi baru.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Hanya pengusul asli yang bisa mengedit, dan hanya jika status masih Pending.
     */
    public function update(User $user, ChangeRequest $changeRequest): bool
    {
        return $user->id === $changeRequest->proposer_id
            && $changeRequest->approval_status === 'Pending';
    }

    /**
     * Hanya role admin atau manajemen yang bisa menghapus pengajuan.
     */
    public function delete(User $user, ChangeRequest $changeRequest): bool
    {
        return in_array($user->role, ['admin', 'manajemen']);
    }

    /**
     * Hanya user dengan jabatan manager yang bisa memberikan persetujuan.
     */
    public function approve(User $user, ChangeRequest $changeRequest): bool
    {
        return $user->jabatan === 'manager';
    }

    /**
     * Hanya role admin atau manajemen yang bisa mengekspor pengajuan ke Word.
     */
    public function export(User $user, ChangeRequest $changeRequest): bool
    {
        return in_array($user->role, ['admin', 'manajemen'])
            && $changeRequest->approval_status !== 'Pending';
    }
}
