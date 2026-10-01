<?php

namespace App\Policies;

use App\Models\Doctor;
use App\Models\User;

/**
 * Fichas del directorio: por permiso (MODELO-IDENTIDAD.md §5). Nunca se borran:
 * se despublican, se suspenden o se fusionan (AGENTS.md §5).
 */
class DoctorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('doctors.view');
    }

    public function view(User $user, Doctor $doctor): bool
    {
        return $user->can('doctors.view');
    }

    public function create(User $user): bool
    {
        return $user->can('doctors.create');
    }

    public function update(User $user, Doctor $doctor): bool
    {
        return $user->can('doctors.update');
    }

    public function publish(User $user, Doctor $doctor): bool
    {
        return $user->can('doctors.publish');
    }

    public function verify(User $user, Doctor $doctor): bool
    {
        return $user->can('doctors.verify');
    }

    public function suspend(User $user, Doctor $doctor): bool
    {
        return $user->can('doctors.suspend');
    }

    public function manageContacts(User $user, Doctor $doctor): bool
    {
        return $user->can('contacts.update');
    }

    public function delete(User $user, Doctor $doctor): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Doctor $doctor): bool
    {
        return false;
    }

    public function restore(User $user, Doctor $doctor): bool
    {
        return false;
    }
}
