<?php

namespace App\Policies;

use App\Models\User;

/**
 * Cuentas del equipo. Los usuarios no se borran: se suspenden (MODELO-IDENTIDAD.md §11).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $record): bool
    {
        return $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user, User $record): bool
    {
        return $user->can('users.update');
    }

    public function suspend(User $user, User $record): bool
    {
        return $user->can('users.suspend');
    }

    public function manageRoles(User $user, User $record): bool
    {
        return $user->can('roles.manage');
    }

    public function delete(User $user, User $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
