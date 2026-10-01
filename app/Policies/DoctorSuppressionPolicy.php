<?php

namespace App\Policies;

use App\Models\DoctorSuppression;
use App\Models\User;

/**
 * Una supresión es el registro de un derecho de oposición: se crea, se consulta y,
 * si la persona quiere volver, se revoca. Nunca se edita ni se borra (DATABASE.md §14.1).
 */
class DoctorSuppressionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('suppressions.manage');
    }

    public function view(User $user, DoctorSuppression $suppression): bool
    {
        return $user->can('suppressions.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('suppressions.manage');
    }

    /**
     * La persona quiere volver al directorio. Se revoca una sola vez (RevokeSuppressionAction).
     */
    public function revoke(User $user, DoctorSuppression $suppression): bool
    {
        return $user->can('suppressions.manage') && ! $suppression->isRevoked();
    }

    public function update(User $user, DoctorSuppression $suppression): bool
    {
        return false;
    }

    public function delete(User $user, DoctorSuppression $suppression): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
