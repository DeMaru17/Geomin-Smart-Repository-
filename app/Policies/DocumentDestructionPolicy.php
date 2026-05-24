<?php

namespace App\Policies;

use App\Models\DocumentDestruction;
use App\Models\User;

class DocumentDestructionPolicy
{
    private function isAdminOrManajemen(User $user): bool
    {
        return in_array($user->role, ['admin', 'manajemen']);
    }

    /**
     * Semua pengguna terautentikasi dapat melihat daftar pemusnahan.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Hanya admin atau manajemen yang dapat membuat pengajuan pemusnahan.
     */
    public function create(User $user): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    /**
     * Hanya admin atau manajemen yang dapat menghapus pengajuan yang masih Pending.
     */
    public function delete(User $user, DocumentDestruction $documentDestruction): bool
    {
        return $this->isAdminOrManajemen($user) && $documentDestruction->status === 'Pending';
    }
}
