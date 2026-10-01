<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Cdn\DoctorCachePurge;
use App\Domain\Directory\Support\PublicationGuard;
use App\Models\Doctor;
use App\Models\User;
use Closure;

/**
 * Todo cambio en el agregado de un médico (especialidades, ubicaciones, contactos)
 * pasa por aquí: se aplica, se comprueba que una ficha publicada siga completa
 * (si no, se revierte), se audita y se purga la CDN.
 */
final class DoctorAggregateChange
{
    /**
     * @param  array<string, mixed>  $audit  detalle para activity_log (qué parte y qué operación)
     */
    public static function apply(Doctor $doctor, ?User $actor, array $audit, Closure $change): mixed
    {
        return DoctorCachePurge::around($doctor, fn () => PublicationGuard::run($doctor, function () use ($doctor, $actor, $audit, $change) {
            $result = $change();

            activity()
                ->performedOn($doctor)
                ->causedBy($actor)
                ->event('doctor.updated')
                ->withProperties($audit)
                ->log('doctor.updated');

            return $result;
        }));
    }
}
