<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Enums\FacilityStatus;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use App\Domain\Directory\Support\FacilityPublicationRequirements;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Activa un establecimiento si pasa su puerta de calidad (DATABASE.md §9.10): al menos
 * una sede activa y un contacto público. Mientras la landing no exista, activo significa
 * "listo para publicar" y no hay nada que purgar en la CDN.
 */
class PublishFacilityAction
{
    /**
     * @throws DirectoryRuleException con los requisitos pendientes en `details`
     */
    public function execute(Facility $facility, ?User $actor): void
    {
        if ($facility->status === FacilityStatus::Active) {
            return;
        }

        if ($missing = FacilityPublicationRequirements::missing($facility)) {
            throw new DirectoryRuleException('El establecimiento no cumple los requisitos para activarse.', array_values($missing));
        }

        DB::transaction(function () use ($facility, $actor) {
            $facility->forceFill(['status' => FacilityStatus::Active])->save();

            activity()
                ->performedOn($facility)
                ->causedBy($actor)
                ->event('facility.published')
                ->log('facility.published');
        });
    }
}
