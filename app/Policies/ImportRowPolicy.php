<?php

namespace App\Policies;

use App\Models\ImportRow;
use App\Models\User;

/**
 * Las filas solo cambian por las Actions de la revisión (ResolveImportRowAction,
 * MapImportValueAction), autorizadas con imports.resolve sobre el lote.
 */
class ImportRowPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('imports.view');
    }

    public function view(User $user, ImportRow $row): bool
    {
        return $user->can('imports.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ImportRow $row): bool
    {
        return false;
    }

    public function delete(User $user, ImportRow $row): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
