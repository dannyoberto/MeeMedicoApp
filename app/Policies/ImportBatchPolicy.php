<?php

namespace App\Policies;

use App\Models\ImportBatch;
use App\Models\User;

/**
 * Lotes de importación: se suben, se revisan, se aplican y se publican. No se editan
 * ni se borran: son el registro permanente de qué ficha nació de qué archivo (§12.2).
 */
class ImportBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('imports.view');
    }

    public function view(User $user, ImportBatch $batch): bool
    {
        return $user->can('imports.view');
    }

    public function create(User $user): bool
    {
        return $user->can('imports.create');
    }

    public function resolve(User $user, ImportBatch $batch): bool
    {
        return $user->can('imports.resolve');
    }

    public function apply(User $user, ImportBatch $batch): bool
    {
        return $user->can('imports.apply');
    }

    public function update(User $user, ImportBatch $batch): bool
    {
        return false;
    }

    public function delete(User $user, ImportBatch $batch): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
