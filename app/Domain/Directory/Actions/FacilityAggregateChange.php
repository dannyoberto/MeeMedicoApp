<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Support\FacilityPublicationRequirements;
use App\Models\Facility;
use App\Models\User;
use Closure;

/**
 * Todo cambio en las sedes, contactos o convenios de un establecimiento pasa por aquí:
 * se aplica, se comprueba que uno activo siga completo (si no, se revierte) y se audita
 * como facility.updated con `part` y `op` (DATABASE.md §14.4).
 *
 * Sin purga de CDN: la landing aún no existe. Cuando exista, se envuelve aquí, como
 * DoctorAggregateChange con DoctorCachePurge.
 */
final class FacilityAggregateChange
{
    /**
     * @param  array<string, mixed>  $audit  detalle para activity_log (qué parte y qué operación)
     */
    public static function apply(Facility $facility, ?User $actor, array $audit, Closure $change): mixed
    {
        return FacilityPublicationRequirements::guard($facility, function () use ($facility, $actor, $audit, $change) {
            $result = $change();

            activity()
                ->performedOn($facility)
                ->causedBy($actor)
                ->event('facility.updated')
                ->withProperties($audit)
                ->log('facility.updated');

            return $result;
        });
    }
}
