<?php

namespace App\Policies;

/**
 * Redes operadoras: un catálogo pequeño por país, gestionado con un solo permiso.
 */
class FacilityNetworkPolicy extends CatalogPolicy
{
    protected function viewPermission(): string
    {
        return 'networks.manage';
    }

    protected function createPermission(): string
    {
        return 'networks.manage';
    }

    protected function updatePermission(): string
    {
        return 'networks.manage';
    }
}
