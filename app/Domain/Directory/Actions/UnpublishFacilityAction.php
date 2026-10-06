<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Enums\FacilityStatus;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Desactiva un establecimiento. Sus sedes, contactos y médicos se conservan: los médicos
 * siguen atendiendo en esas direcciones aunque el establecimiento no se muestre.
 */
class UnpublishFacilityAction
{
    public function execute(Facility $facility, ?User $actor, ?string $reason = null): void
    {
        if ($facility->status !== FacilityStatus::Active) {
            return;
        }

        DB::transaction(function () use ($facility, $actor, $reason) {
            $facility->forceFill(['status' => FacilityStatus::Inactive])->save();

            activity()
                ->performedOn($facility)
                ->causedBy($actor)
                ->event('facility.unpublished')
                ->withProperties(array_filter(['reason' => $reason]))
                ->log('facility.unpublished');
        });
    }
}
