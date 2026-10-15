<?php

namespace App\Domain\Directory\Support;

use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Enums\SlugRedirectEntity;
use App\Domain\Directory\Enums\SpecialtyStatus;
use App\Domain\Geo\Enums\CityStatus;
use App\Models\City;
use App\Models\Country;
use App\Models\Doctor;
use App\Models\SlugRedirect;
use App\Models\Specialty;

/**
 * Resuelve el slug de una URL pública a la entidad que hay que mostrar HOY.
 *
 * Si el slug es antiguo (slug_redirects) o el médico se fusionó, devuelve la entidad
 * vigente: el controlador compara su slug con el pedido y responde 301. Nunca 404 por un
 * slug que alguna vez fue válido (AGENTS.md §3, DATABASE.md §14.2).
 *
 * Devuelve null si no existe o no es pública (borrador, inactiva, de otro país).
 */
final class PublicSlugs
{
    /**
     * Saltos máximos siguiendo fusiones encadenadas (A → B → C). Protege de un ciclo.
     */
    private const MAX_MERGE_HOPS = 5;

    public static function doctor(Country $country, string $slug): ?Doctor
    {
        $doctor = Doctor::where('country_id', $country->id)->where('slug', $slug)->first()
            ?? self::redirected(SlugRedirectEntity::Doctor, $country, $slug, Doctor::class);

        for ($hops = 0; $doctor?->status === DoctorStatus::Merged && $hops < self::MAX_MERGE_HOPS; $hops++) {
            $doctor = $doctor->mergedInto;
        }

        return $doctor?->status === DoctorStatus::Active && $doctor->country_id === $country->id
            ? $doctor
            : null;
    }

    /**
     * El catálogo de especialidades es global: sus redirecciones no tienen país.
     */
    public static function specialty(string $slug): ?Specialty
    {
        $specialty = Specialty::where('slug', $slug)->first()
            ?? self::redirected(SlugRedirectEntity::Specialty, null, $slug, Specialty::class);

        return $specialty?->status === SpecialtyStatus::Active ? $specialty : null;
    }

    public static function city(Country $country, string $slug): ?City
    {
        $city = City::where('country_id', $country->id)->where('slug', $slug)->first()
            ?? self::redirected(SlugRedirectEntity::City, $country, $slug, City::class);

        return $city?->status === CityStatus::Active && $city->country_id === $country->id ? $city : null;
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<T>  $model
     * @return T|null
     */
    private static function redirected(SlugRedirectEntity $entity, ?Country $country, string $slug, string $model): mixed
    {
        $id = SlugRedirect::where('entity_type', $entity)
            ->where('country_id', $country?->id)
            ->where('old_slug', $slug)
            ->value('entity_id');

        return $id ? $model::find($id) : null;
    }
}
