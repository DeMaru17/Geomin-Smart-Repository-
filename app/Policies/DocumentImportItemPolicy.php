<?php

namespace App\Policies;

use App\Models\DocumentImportItem;
use App\Models\User;

class DocumentImportItemPolicy
{
    private function isAdminOrManajemen(User $user): bool
    {
        return in_array($user->role, ['admin', 'manajemen'], true);
    }

    public function viewAny(User $user): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    public function view(User $user, DocumentImportItem $item): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    public function update(User $user, DocumentImportItem $item): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    public function delete(User $user, DocumentImportItem $item): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    public function install(User $user, DocumentImportItem $item): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    public function recover(User $user, DocumentImportItem $item): bool
    {
        return $this->isAdminOrManajemen($user);
    }
}
