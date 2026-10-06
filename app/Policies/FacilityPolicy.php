<?php

namespace App\Policies;

use App\Models\Facility;
use App\Models\User;

/**
 * Establecimientos (MODULO-ESTABLECIMIENTOS-SEGUROS.md §7): solo admin en Fase 1.
 * No se borran ni se desactivan como un catálogo: dejan de estar activos con
 * UnpublishFacilityAction, autorizado por facilities.publish.
 */
class FacilityPolicy extends CatalogPolicy
{
    protected function viewPermission(): string
    {
        return 'facilities.view';
    }

    protected function createPermission(): string
    {
        return 'facilities.create';
    }

    protected function updatePermission(): string
    {
        return 'facilities.update';
    }

    protected function deactivatePermission(): string
    {
        return 'facilities.delete';
    }

    public function publish(User $user, Facility $facility): bool
    {
        return $user->can('facilities.publish');
    }
}
