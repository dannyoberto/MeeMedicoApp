<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Los alias se gestionan desde la especialidad: editar la especialidad incluye sus alias.
 * Como en CityAliasPolicy, borrar un alias incorrecto es la forma de corregirlo.
 */
class SpecialtyAliasPolicy extends CatalogPolicy
{
    protected function viewPermission(): string
    {
        return 'specialties.view';
    }

    protected function createPermission(): string
    {
        return 'specialties.update';
    }

    protected function updatePermission(): string
    {
        return 'specialties.update';
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->can('specialties.update');
    }
}
