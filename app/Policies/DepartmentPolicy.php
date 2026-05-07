<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DepartmentPolicy
{
    private function isAdminOrManajemen(User $user): bool
    {
        return in_array($user->role, ['admin', 'manajemen']);
    }

    public function viewAny(User $user): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    public function view(User $user, Department $department): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    public function update(User $user, Department $department): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    public function delete(User $user, Department $department): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    public function restore(User $user, Department $department): bool
    {
        return $this->isAdminOrManajemen($user);
    }

    public function forceDelete(User $user, Department $department): bool
    {
        return $this->isAdminOrManajemen($user);
    }
}
