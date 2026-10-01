<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Exceptions\IdentityRuleException;
use App\Domain\Identity\Support\BackofficeAccessGuard;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Suspende una cuenta: los usuarios no se borran (MODELO-IDENTIDAD.md §11).
 * La suspensión surte efecto de inmediato, no en el próximo login.
 */
class SuspendUserAction
{
    /**
     * @throws IdentityRuleException
     */
    public function execute(User $user, ?User $actor, string $reason): void
    {
        if ($user->status === UserStatus::Suspended) {
            return;
        }

        BackofficeAccessGuard::assertCanLoseAccess($user, $actor, 'suspender');

        DB::transaction(function () use ($user, $actor, $reason) {
            $user->forceFill([
                'status' => UserStatus::Suspended,
                // Invalida la cookie "recordarme": sin esto, volvería a entrar sin contraseña.
                'remember_token' => Str::random(60),
            ])->save();

            // Cierra sus sesiones abiertas y revoca sus tokens de API.
            DB::table('sessions')->where('user_id', $user->getKey())->delete();
            $user->tokens()->delete();

            activity()
                ->performedOn($user)
                ->causedBy($actor)
                ->event('user.suspended')
                ->withProperties(['reason' => $reason])
                ->log('user.suspended');
        });
    }
}
