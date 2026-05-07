<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DocumentPolicy
{
    private function isAdminOrManajemen(User $user): bool
    {
        return in_array($user->role, ['admin', 'manajemen']);
    }

    public function viewAny(User $user): bool
    {
        return true; // Semua user bisa melihat daftar dokumen
    }

    public function view(User $user, Document $document): bool
    {
        return true; // Semua user bisa melihat detail dokumen
    }

    public function create(User $user): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    public function update(User $user, Document $document): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    public function restore(User $user, Document $document): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    public function forceDelete(User $user, Document $document): bool
    {
        return $this->isAdminOrManajemen($user);
    }
}
