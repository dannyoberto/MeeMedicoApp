<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Cdn\DoctorPublicPaths;
use App\Domain\Directory\Cdn\PurgeCdnPaths;
use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Geo\Enums\CityStatus;
use App\Domain\Geo\Support\Normalize;
use App\Models\City;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alta y edición de una ubicación. País y región se derivan de la ciudad: la FK
 * compuesta los exige coherentes y no se eligen a mano. address_normalized la
 * escribe la app (§3.6) y sirve al importador para detectar la misma dirección.
 */
class SaveLocationAction
{
    private const FIELDS = ['city_id', 'name', 'address', 'address_2', 'postal_code', 'latitude', 'longitude'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor): Location
    {
        $location = new Location(['created_by_user_id' => $actor?->getKey()]);
        $this->fill($location, $data);
        $location->save();

        return $location;
    }

    /**
     * Una ubicación puede estar compartida: editarla cambia la ficha de todos sus médicos.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Location $location, array $data, ?User $actor): Location
    {
        $published = fn () => $location->doctors()->where('doctors.status', DoctorStatus::Active)->get();
        $before = $published()->flatMap(fn ($d) => DoctorPublicPaths::for($d))->all();

        DB::transaction(function () use ($location, $data, $actor) {
            $this->fill($location, $data);
            $changes = array_keys($location->getDirty());
            $location->save();

            if ($changes !== []) {
                activity()->performedOn($location)->causedBy($actor)->event('location.updated')
                    ->withProperties(['fields' => $changes])->log('location.updated');
            }
        });

        $after = $published()->flatMap(fn ($d) => DoctorPublicPaths::for($d))->all();
        if ($paths = array_values(array_unique([...$before, ...$after]))) {
            PurgeCdnPaths::dispatch($paths);
        }

        return $location;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fill(Location $location, array $data): void
    {
        $data = array_intersect_key($data, array_flip(self::FIELDS));

        if (array_key_exists('city_id', $data)) {
            $city = City::find($data['city_id']);
            if (! $city || $city->status !== CityStatus::Active) {
                throw ValidationException::withMessages(['city_id' => 'Elige una ciudad activa.']);
            }
            $data['region_id'] = $city->region_id;
            $data['country_id'] = $city->country_id;
        }

        $hasLat = filled($data['latitude'] ?? $location->latitude);
        $hasLng = filled($data['longitude'] ?? $location->longitude);
        if ($hasLat !== $hasLng) {
            throw ValidationException::withMessages(['latitude' => 'Indica latitud y longitud juntas, o ninguna.']);
        }

        $location->fill($data);
        $location->address_normalized = Normalize::text(trim("{$location->address} {$location->address_2}"));
    }
}
