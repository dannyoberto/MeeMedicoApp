<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Enums\LocationStatus;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use App\Models\City;
use App\Models\Facility;
use App\Models\Location;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Sedes de un establecimiento (DATABASE.md §9.11). El establecimiento es dueño de la
 * ubicación; los médicos siguen vinculándose a ella y por eso son "del establecimiento".
 *
 * Una ubicación pertenece como máximo a un establecimiento y nunca cambia de dueño en
 * silencio: reasignarla es move(), una acción explícita. Quitar una sede no la borra
 * ni desvincula a sus médicos: solo deja de ser del establecimiento.
 */
class FacilityLocationsAction
{
    public function __construct(private readonly SaveLocationAction $saveLocation) {}

    /**
     * Crea una dirección nueva como sede.
     *
     * @param  array<string, mixed>  $locationData  ver SaveLocationAction
     *
     * @throws ValidationException (clave: city_id)
     */
    public function create(Facility $facility, array $locationData, ?User $actor): Location
    {
        $city = City::find($locationData['city_id'] ?? null);
        if ($city && $city->country_id !== $facility->country_id) {
            throw ValidationException::withMessages(['city_id' => 'La sede debe estar en el mismo país que el establecimiento.']);
        }

        return FacilityAggregateChange::apply($facility, $actor, ['part' => 'locations', 'op' => 'created'], function () use ($facility, $locationData, $actor) {
            $location = $this->saveLocation->create($locationData, $actor);
            $location->forceFill(['facility_id' => $facility->getKey()])->save();

            return $location;
        });
    }

    /**
     * Convierte en sede una ubicación existente que no es de ningún establecimiento: por
     * ejemplo, la "Torre Médica" que el importador creó como dirección suelta.
     *
     * @throws DirectoryRuleException
     */
    public function assign(Facility $facility, Location $location, ?User $actor): void
    {
        $this->assertAssignable($facility, $location);

        if ($location->facility_id !== null) {
            throw new DirectoryRuleException($location->facility_id === $facility->getKey()
                ? 'Esa ubicación ya es una sede de este establecimiento.'
                : 'Esa ubicación es sede de '.$location->facility->name.'. Para cambiarla de establecimiento, usa "Mover".');
        }

        FacilityAggregateChange::apply($facility, $actor, ['part' => 'locations', 'op' => 'assigned', 'location' => $location->getKey()], function () use ($facility, $location) {
            $location->forceFill(['facility_id' => $facility->getKey()])->save();
        });
    }

    /**
     * Pasa una sede de otro establecimiento a este. Los contactos que el anterior tenía
     * en esa sede quedan como contactos generales suyos: no viajan con la dirección.
     *
     * @throws DirectoryRuleException si deja incompleto al establecimiento de origen activo
     */
    public function move(Facility $facility, Location $location, ?User $actor): void
    {
        $this->assertAssignable($facility, $location);

        $from = $location->facility;
        if (! $from) {
            $this->assign($facility, $location, $actor);

            return;
        }
        if ($from->is($facility)) {
            throw new DirectoryRuleException('Esa ubicación ya es una sede de este establecimiento.');
        }

        // Todo o nada: si el origen activo se queda sin sede, no se mueve.
        FacilityAggregateChange::apply($from, $actor, ['part' => 'locations', 'op' => 'moved_out', 'location' => $location->getKey(), 'to' => $facility->getKey()], function () use ($facility, $from, $location, $actor) {
            $this->release($from, $location);

            FacilityAggregateChange::apply($facility, $actor, ['part' => 'locations', 'op' => 'moved_in', 'location' => $location->getKey(), 'from' => $from->getKey()], function () use ($facility, $location) {
                $location->forceFill(['facility_id' => $facility->getKey()])->save();
            });
        });
    }

    /**
     * La ubicación deja de ser sede. Sus médicos la conservan.
     *
     * @throws DirectoryRuleException si el establecimiento activo se queda sin sede activa
     */
    public function detach(Facility $facility, Location $location, ?User $actor): void
    {
        if ($location->facility_id !== $facility->getKey()) {
            throw new DirectoryRuleException('Esa ubicación no es una sede de este establecimiento.');
        }

        FacilityAggregateChange::apply($facility, $actor, ['part' => 'locations', 'op' => 'detached', 'location' => $location->getKey()], function () use ($facility, $location) {
            $this->release($facility, $location);
        });
    }

    /**
     * Desvincula primero los contactos de esa sede (§9.12) y después la sede.
     */
    private function release(Facility $facility, Location $location): void
    {
        $facility->contacts()->where('location_id', $location->getKey())->update(['location_id' => null]);
        $location->forceFill(['facility_id' => null])->save();
    }

    private function assertAssignable(Facility $facility, Location $location): void
    {
        if ($location->country_id !== $facility->country_id) {
            throw new DirectoryRuleException('La sede debe estar en el mismo país que el establecimiento.');
        }

        if ($location->status !== LocationStatus::Active) {
            throw new DirectoryRuleException('Esa ubicación está inactiva.');
        }
    }
}
