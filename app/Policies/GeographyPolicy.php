<?php

namespace App\Policies;

/**
 * Toda la geografía se gobierna con un único permiso (DATABASE.md §16.2).
 */
abstract class GeographyPolicy extends CatalogPolicy
{
    protected function viewPermission(): string
    {
        return 'geography.manage';
    }

    protected function createPermission(): string
    {
        return 'geography.manage';
    }

    protected function updatePermission(): string
    {
        return 'geography.manage';
    }
}
