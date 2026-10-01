<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Base de las Policies de catálogos: autoriza siempre por permiso, nunca por rol
 * (MODELO-IDENTIDAD.md §5). Los permisos viven en PermissionSeeder.
 *
 * Nada se borra físicamente (AGENTS.md §5): delete/forceDelete/restore son siempre
 * false y la baja es `deactivate`, que cambia status.
 */
abstract class CatalogPolicy
{
    abstract protected function viewPermission(): string;

    abstract protected function createPermission(): string;

    abstract protected function updatePermission(): string;

    /**
     * Permiso para desactivar/activar. Por defecto, el de edición.
     */
    protected function deactivatePermission(): string
    {
        return $this->updatePermission();
    }

    public function viewAny(User $user): bool
    {
        return $user->can($this->viewPermission());
    }

    public function view(User $user, Model $record): bool
    {
        return $user->can($this->viewPermission());
    }

    public function create(User $user): bool
    {
        return $user->can($this->createPermission());
    }

    public function update(User $user, Model $record): bool
    {
        return $user->can($this->updatePermission());
    }

    public function deactivate(User $user, Model $record): bool
    {
        return $user->can($this->deactivatePermission());
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Model $record): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }
}
