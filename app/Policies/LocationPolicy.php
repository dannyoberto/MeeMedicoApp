<?php

namespace App\Policies;

/**
 * Ubicaciones: se desactivan, no se borran; `locations.delete` autoriza la baja lógica.
 * Regla de Fase 1 (MODELO-DOMINIO.md §2.4): una ubicación compartida solo la edita un
 * administrador. Hoy todo usuario del backoffice lo es; se aplicará al panel del médico.
 */
class LocationPolicy extends CatalogPolicy
{
    protected function viewPermission(): string
    {
        return 'locations.view';
    }

    protected function createPermission(): string
    {
        return 'locations.create';
    }

    protected function updatePermission(): string
    {
        return 'locations.update';
    }

    protected function deactivatePermission(): string
    {
        return 'locations.delete';
    }
}
