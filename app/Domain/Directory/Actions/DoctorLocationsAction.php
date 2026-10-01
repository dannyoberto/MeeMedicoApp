<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Enums\LocationStatus;
use App\Domain\Directory\Enums\LocationType;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use App\Models\Doctor;
use App\Models\Location;
use App\Models\User;

/**
 * Ubicaciones de un médico. La ubicación es compartible (la torre médica): quitarla
 * de una ficha la desasocia, nunca la borra. La principal decide en qué listado de
 * ciudad aparece el médico (MODELO-DOMINIO.md §2.4).
 */
class DoctorLocationsAction
{
    public function __construct(private readonly SaveLocationAction $saveLocation) {}

    /**
     * @param  array<string, mixed>  $locationData  datos de una ubicación nueva (ver SaveLocationAction)
     */
    public function attachNew(Doctor $doctor, array $locationData, LocationType $type, ?User $actor, bool $primary = false): Location
    {
        return DoctorAggregateChange::apply($doctor, $actor, ['part' => 'locations', 'op' => 'attached_new'], function () use ($doctor, $locationData, $type, $actor, $primary) {
            $location = $this->saveLocation->create($locationData, $actor);
            $this->link($doctor, $location, $type, $primary);

            return $location;
        });
    }

    public function attachExisting(Doctor $doctor, Location $location, LocationType $type, ?User $actor, bool $primary = false): void
    {
        if ($location->status !== LocationStatus::Active) {
            throw new DirectoryRuleException('Esa ubicación está inactiva.');
        }

        if ($doctor->locations()->whereKey($location->getKey())->exists()) {
            throw new DirectoryRuleException('Esa ubicación ya está asociada a esta ficha.');
        }

        DoctorAggregateChange::apply($doctor, $actor, ['part' => 'locations', 'op' => 'attached_existing', 'location' => $location->getKey()], function () use ($doctor, $location, $type, $primary) {
            $this->link($doctor, $location, $type, $primary);
        });
    }

    public function detach(Doctor $doctor, Location $location, ?User $actor): void
    {
        DoctorAggregateChange::apply($doctor, $actor, ['part' => 'locations', 'op' => 'detached', 'location' => $location->getKey()], function () use ($doctor, $location) {
            $wasPrimary = (bool) $doctor->locations()->whereKey($location->getKey())->first()?->pivot->is_primary;

            $doctor->locations()->detach($location->getKey());

            if ($wasPrimary) {
                $next = $doctor->locations()->where('locations.status', LocationStatus::Active)->orderByPivot('created_at')->first();
                $next && $doctor->locations()->updateExistingPivot($next->getKey(), ['is_primary' => true]);
            }
        });
    }

    public function makePrimary(Doctor $doctor, Location $location, ?User $actor): void
    {
        DoctorAggregateChange::apply($doctor, $actor, ['part' => 'locations', 'op' => 'primary', 'location' => $location->getKey()], function () use ($doctor, $location) {
            $this->clearPrimary($doctor);
            $doctor->locations()->updateExistingPivot($location->getKey(), ['is_primary' => true]);
        });
    }

    public function changeType(Doctor $doctor, Location $location, LocationType $type, ?User $actor): void
    {
        DoctorAggregateChange::apply($doctor, $actor, ['part' => 'locations', 'op' => 'type', 'location' => $location->getKey(), 'type' => $type->value], function () use ($doctor, $location, $type) {
            $doctor->locations()->updateExistingPivot($location->getKey(), ['location_type' => $type->value]);
        });
    }

    private function link(Doctor $doctor, Location $location, LocationType $type, bool $primary): void
    {
        $makePrimary = $primary || ! $doctor->locations()->wherePivot('is_primary', true)->exists();

        if ($makePrimary) {
            $this->clearPrimary($doctor);
        }

        $doctor->locations()->attach($location->getKey(), ['location_type' => $type->value, 'is_primary' => $makePrimary]);
    }

    private function clearPrimary(Doctor $doctor): void
    {
        $doctor->locations()->newPivotStatement()
            ->where('doctor_id', $doctor->getKey())
            ->update(['is_primary' => false]);
    }
}
