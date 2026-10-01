<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * La auditoría es de solo lectura para todos: un registro que se puede editar no prueba nada.
 * Registrada con Gate::policy() en AppServiceProvider (el modelo no está en App\Models).
 */
class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('activity.view');
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->can('activity.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Activity $activity): bool
    {
        return false;
    }

    public function delete(User $user, Activity $activity): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
