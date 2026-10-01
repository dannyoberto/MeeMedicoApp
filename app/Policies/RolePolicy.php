<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * Solo lectura: qué permisos tiene cada rol lo define PermissionSeeder, que los
 * resincroniza en cada ejecución. Un cambio hecho en el panel se perdería.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('roles.manage');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('roles.manage');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Role $role): bool
    {
        return false;
    }

    public function delete(User $user, Role $role): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
