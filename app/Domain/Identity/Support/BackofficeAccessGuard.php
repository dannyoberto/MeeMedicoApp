<?php

namespace App\Domain\Identity\Support;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Exceptions\IdentityRuleException;
use App\Models\User;

/**
 * Evita dejar el backoffice inaccesible: nadie se quita su propio acceso y siempre
 * queda al menos un usuario activo con `backoffice.access`. Se razona por permiso,
 * no por rol, igual que el resto de la autorización (MODELO-IDENTIDAD.md §5).
 */
final class BackofficeAccessGuard
{
    public const PERMISSION = 'backoffice.access';

    /**
     * @param  User  $target  usuario que perdería el acceso
     * @param  User|null  $actor  quien ejecuta la operación (null desde consola)
     * @param  string  $verb  para el mensaje: "suspender", "quitar el acceso a"…
     *
     * @throws IdentityRuleException
     */
    public static function assertCanLoseAccess(User $target, ?User $actor, string $verb): void
    {
        if (! $target->hasPermissionTo(self::PERMISSION)) {
            return;
        }

        if ($actor?->is($target)) {
            throw new IdentityRuleException("No puedes {$verb} tu propia cuenta: otro administrador debe hacerlo.");
        }

        $others = User::permission(self::PERMISSION)
            ->where('status', UserStatus::Active)
            ->whereKeyNot($target->getKey())
            ->count();

        if ($others === 0) {
            throw new IdentityRuleException("No se puede {$verb} esta cuenta: es el último usuario activo con acceso al backoffice.");
        }
    }
}
