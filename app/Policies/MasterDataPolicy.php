<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Aturan akses master data: semua peran boleh MELIHAT, hanya Admin yang boleh mengubah.
 * Filament membaca Policy ini secara otomatis untuk halaman dan tombol bawaan
 * (New, Edit, Delete, Bulk delete, dst).
 */
abstract class MasterDataPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $record): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->isAdmin($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function restore(User $user, Model $record): bool
    {
        return $this->isAdmin($user);
    }

    public function restoreAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return $this->isAdmin($user);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function replicate(User $user, Model $record): bool
    {
        return $this->isAdmin($user);
    }

    public function reorder(User $user): bool
    {
        return $this->isAdmin($user);
    }

    protected function isAdmin(User $user): bool
    {
        $role = $user->role;

        // Tahan terhadap cast yang belum terpasang (role berupa string)
        if (! $role instanceof UserRole) {
            $role = UserRole::tryFrom((string) $role);
        }

        return $role === UserRole::Admin;
    }
}
