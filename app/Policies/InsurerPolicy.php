<?php

namespace App\Policies;

/**
 * Aseguradoras: catálogo por país, solo admin en Fase 1. `insurers.delete` autoriza
 * la baja lógica (status), nunca el borrado.
 */
class InsurerPolicy extends CatalogPolicy
{
    protected function viewPermission(): string
    {
        return 'insurers.view';
    }

    protected function createPermission(): string
    {
        return 'insurers.create';
    }

    protected function updatePermission(): string
    {
        return 'insurers.update';
    }

    protected function deactivatePermission(): string
    {
        return 'insurers.delete';
    }
}
