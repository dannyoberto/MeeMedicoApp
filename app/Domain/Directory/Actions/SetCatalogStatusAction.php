<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Enums\FacilityStatus;
use App\Domain\Directory\Enums\LocationStatus;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use App\Models\City;
use App\Models\Country;
use App\Models\Doctor;
use App\Models\FacilityNetwork;
use App\Models\Language;
use App\Models\Location;
use App\Models\Region;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Activa o desactiva una entrada de catálogo (baja lógica, AGENTS.md §5).
 *
 * Desactivar algo que usa una ficha PUBLICADA se rechaza: la ficha dejaría de cumplir
 * los requisitos o desaparecería de un listado indexado sin que nadie lo decidiera.
 * Primero se reasignan o despublican esas fichas.
 */
class SetCatalogStatusAction
{
    public function execute(Model $entity, bool $active, ?User $actor): void
    {
        $status = $active ? 'active' : 'inactive';

        if ($entity->getAttribute('status')?->value === $status) {
            return;
        }

        if (! $active && ($inUse = $this->publishedDoctorsUsing($entity)?->count())) {
            throw new DirectoryRuleException(
                "No se puede desactivar: la usan {$inUse} ficha(s) publicada(s). Reasígnalas o despublícalas primero.",
            );
        }

        if (! $active && $entity instanceof Location && $this->isLastActiveSedeOfActiveFacility($entity)) {
            throw new DirectoryRuleException(
                "No se puede desactivar: es la única sede activa de {$entity->facility->name}. Añade otra sede o desactiva el establecimiento primero.",
            );
        }

        DB::transaction(function () use ($entity, $status, $actor) {
            $entity->forceFill(['status' => $status])->save();

            activity()->performedOn($entity)->causedBy($actor)
                ->event($status === 'active' ? 'catalog.activated' : 'catalog.deactivated')
                ->log($status === 'active' ? 'catalog.activated' : 'catalog.deactivated');
        });
    }

    /**
     * @return Builder<Doctor>|null
     */
    private function publishedDoctorsUsing(Model $entity): ?Builder
    {
        $published = Doctor::query()->where('status', DoctorStatus::Active);

        return match (true) {
            $entity instanceof Country => $published->where('country_id', $entity->getKey()),
            $entity instanceof Region => $published->whereHas('locations', fn ($q) => $q->where('locations.region_id', $entity->getKey())),
            $entity instanceof City => $published->whereHas('locations', fn ($q) => $q->where('locations.city_id', $entity->getKey())),
            $entity instanceof Location => $published->whereHas('locations', fn ($q) => $q->whereKey($entity->getKey())),
            $entity instanceof Specialty => $published->whereHas('specialties', fn ($q) => $q->whereKey($entity->getKey())),
            $entity instanceof Language => null, // un idioma no condiciona la publicación
            $entity instanceof FacilityNetwork => null, // la red tampoco: sus establecimientos la conservan
            default => null,
        };
    }

    /**
     * Un establecimiento activo nunca queda sin sede activa (§9.10), igual que una ficha
     * publicada nunca queda sin ubicación.
     */
    private function isLastActiveSedeOfActiveFacility(Location $location): bool
    {
        $facility = $location->facility;

        return $facility?->status === FacilityStatus::Active
            && ! $facility->locations()->where('status', LocationStatus::Active)->whereKeyNot($location->getKey())->exists();
    }
}
