<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Exceptions\DirectoryRuleException;
use App\Models\DoctorSuppression;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * La persona que pidió no aparecer quiere volver (DATABASE.md §14.1). La supresión no
 * se borra ni se edita: se revoca una sola vez y queda como traza legal. Si vuelve a
 * pedir salir, se registra una supresión nueva.
 *
 * Revocar no republica nada: las fichas vuelven al sitio pasando por PublishDoctorAction.
 */
class RevokeSuppressionAction
{
    /**
     * @throws ValidationException (claves: reason, requested_at)
     * @throws DirectoryRuleException si ya estaba revocada
     */
    public function execute(DoctorSuppression $suppression, ?string $reason, CarbonInterface $requestedAt, ?User $actor): DoctorSuppression
    {
        if ($suppression->isRevoked()) {
            throw new DirectoryRuleException('Esta supresión ya fue revocada el '.$suppression->revoked_at->format('d/m/Y').'.');
        }

        $reason = filled($reason) ? trim($reason) : null;
        if ($reason === null) {
            throw ValidationException::withMessages([
                'reason' => 'Indica por qué canal llegó la solicitud y cómo se verificó la identidad.',
            ]);
        }

        // Por día: el selector de fecha no tiene segundos y la supresión puede ser de hace un momento.
        if ($requestedAt->isFuture() || $requestedAt->toDateString() < $suppression->requested_at->toDateString()) {
            throw ValidationException::withMessages([
                'requested_at' => 'La fecha debe ser posterior a la supresión y no futura.',
            ]);
        }

        return DB::transaction(function () use ($suppression, $reason, $requestedAt, $actor) {
            $suppression->forceFill([
                'revocation_requested_at' => $requestedAt,
                'revocation_reason' => $reason,
                'revoked_by_user_id' => $actor?->getKey(),
                'revoked_at' => now(),
            ])->save();

            activity()
                ->performedOn($suppression)
                ->causedBy($actor)
                ->event('suppression.revoked')
                ->withProperties(['reason' => $reason])
                ->log('suppression.revoked');

            return $suppression;
        });
    }
}
