<?php

namespace App\Policies;

class SpecialtyPolicy extends CatalogPolicy
{
    protected function viewPermission(): string
    {
        return 'specialties.view';
    }

    protected function createPermission(): string
    {
        return 'specialties.create';
    }

    protected function updatePermission(): string
    {
        return 'specialties.update';
    }

    /**
     * specialties.delete de §16.2 autoriza la baja lógica, no el borrado físico.
     */
    protected function deactivatePermission(): string
    {
        return 'specialties.delete';
    }
}
