<?php

namespace App\Policies;

use App\Models\DocumentImportBatch;
use App\Models\User;

class DocumentImportBatchPolicy
{
    private function isAdminOrManajemen(User $user): bool
    {
        return in_array($user->role, ['admin', 'manajemen'], true);
    }

    public function viewAny(User $user): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    public function view(User $user, DocumentImportBatch $batch): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    public function update(User $user, DocumentImportBatch $batch): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    public function delete(User $user, DocumentImportBatch $batch): bool
    {
        return $this->isAdminOrManajemen($user);
    }
}
