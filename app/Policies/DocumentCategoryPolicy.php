<?php

namespace App\Policies;

use App\Models\DocumentCategory;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DocumentCategoryPolicy
{
    private function isAdminOrManajemen(User $user): bool
    {
        return in_array($user->role, ['admin', 'manajemen']);
    }

    public function viewAny(User $user): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    public function view(User $user, DocumentCategory $documentCategory): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    public function update(User $user, DocumentCategory $documentCategory): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    public function delete(User $user, DocumentCategory $documentCategory): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    public function restore(User $user, DocumentCategory $documentCategory): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    public function forceDelete(User $user, DocumentCategory $documentCategory): bool
    {
        return $this->isAdminOrManajemen($user);
    }
}
