<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Exceptions\IdentityRuleException;
use App\Domain\Identity\Support\BackofficeAccessGuard;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Asigna y retira roles. Qué permisos tiene cada rol no se decide aquí: lo fija
 * PermissionSeeder.
 */
class SyncUserRolesAction
{
    /**
     * @param  array<int, string>  $roles  nombres de rol que el usuario debe tener
     *
     * @throws IdentityRuleException
     */
    public function execute(User $user, array $roles, ?User $actor): void
    {
        $current = $user->getRoleNames()->all();
        $added = array_values(array_diff($roles, $current));
        $removed = array_values(array_diff($current, $roles));

        if ($added === [] && $removed === []) {
            return;
        }

        $keepsAccess = Role::whereIn('name', $roles)
            ->whereHas('permissions', fn ($q) => $q->where('name', BackofficeAccessGuard::PERMISSION))
            ->exists()
            || $user->getDirectPermissions()->contains('name', BackofficeAccessGuard::PERMISSION);

        if (! $keepsAccess) {
            BackofficeAccessGuard::assertCanLoseAccess($user, $actor, 'quitar el acceso al backoffice de');
        }

        DB::transaction(function () use ($user, $roles, $added, $removed, $actor) {
            $user->syncRoles($roles);

            foreach ([['role.assigned', $added], ['role.removed', $removed]] as [$event, $names]) {
                foreach ($names as $role) {
                    activity()
                        ->performedOn($user)
                        ->causedBy($actor)
                        ->event($event)
                        ->withProperties(['role' => $role])
                        ->log($event);
                }
            }
        });
    }
}
