<?php

namespace App\Domain\Search;

use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Enums\LocationStatus;
use App\Domain\Directory\Enums\SpecialtyStatus;
use App\Domain\Directory\Support\DoctorSearch;
use App\Domain\Geo\Enums\CityStatus;
use App\Models\City;
use App\Models\Country;
use App\Models\Doctor;
use App\Models\Specialty;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Driver de Fase 1 (ARQUITECTURA.md §7): filtros por especialidad, ciudad e idioma más
 * el tsvector del nombre (DATABASE.md §9.1). search_vector no incluye especialidad ni
 * ciudad, que se filtran por sus tablas.
 */
final class PostgresDoctorSearch implements DoctorSearchService
{
    public function search(SearchCriteria $criteria, int $perPage = 20): LengthAwarePaginator
    {
        return $this->filtered($criteria)
            ->leftJoin('doctor_profiles', 'doctor_profiles.doctor_id', '=', 'doctors.id')
            ->select('doctors.*')
            // Orden determinista, para que la página cacheada sea siempre la misma:
            // primero verificados, luego perfiles con foto y con bio, luego apellido.
            ->orderByRaw("doctors.verification_status = 'verified' DESC")
            ->orderByRaw('doctor_profiles.profile_photo_path IS NOT NULL DESC')
            ->orderByRaw('doctor_profiles.bio IS NOT NULL DESC')
            ->orderBy('doctors.last_name')
            ->orderBy('doctors.first_name')
            ->orderBy('doctors.id')
            ->with(self::cardRelations())
            ->paginate($perPage, ['doctors.*'], 'page', $criteria->page);
    }

    public function cityFacets(SearchCriteria $criteria): Collection
    {
        $totals = $this->filtered($criteria->without('city'))
            ->join('doctor_locations', fn ($join) => $join
                ->on('doctor_locations.doctor_id', '=', 'doctors.id')
                ->where('doctor_locations.is_primary', true))
            ->join('locations', 'locations.id', '=', 'doctor_locations.location_id')
            ->where('locations.status', LocationStatus::Active)
            ->groupBy('locations.city_id')
            ->selectRaw('locations.city_id, count(*) as total')
            ->toBase()
            ->pluck('total', 'city_id');

        $cities = City::whereIn('id', $totals->keys())
            ->where('status', CityStatus::Active)
            ->get();

        return $cities
            ->map(fn (City $city) => ['city' => $city, 'total' => (int) $totals[$city->id]])
            ->sortBy([['total', 'desc'], fn ($a, $b) => strcmp($a['city']->name, $b['city']->name)])
            ->values();
    }

    public function specialtyFacets(Country $country): Collection
    {
        $totals = DB::table('doctors')
            ->join('doctor_specialties', 'doctor_specialties.doctor_id', '=', 'doctors.id')
            ->join('specialties', 'specialties.id', '=', 'doctor_specialties.specialty_id')
            ->where('doctors.country_id', $country->id)
            ->where('doctors.status', DoctorStatus::Active->value)
            ->where('specialties.status', SpecialtyStatus::Active->value)
            ->groupByRaw('coalesce(specialties.parent_id, specialties.id)')
            ->selectRaw('coalesce(specialties.parent_id, specialties.id) as top_id, count(distinct doctors.id) as total')
            ->pluck('total', 'top_id');

        return Specialty::whereIn('id', $totals->keys())
            ->where('status', SpecialtyStatus::Active)
            ->get()
            ->map(fn (Specialty $specialty) => ['specialty' => $specialty, 'total' => (int) $totals[$specialty->id]])
            ->sortBy([['total', 'desc'], fn ($a, $b) => strcmp($a['specialty']->name, $b['specialty']->name)])
            ->values();
    }

    /**
     * Relaciones que pinta una tarjeta de resultado; la ficha pide las suyas.
     *
     * @return array<int|string, mixed>
     */
    public static function cardRelations(): array
    {
        return [
            'country:id,slug,code',
            'profile:id,doctor_id,headline,profile_photo_path',
            'specialties' => fn ($q) => $q
                ->where('specialties.status', SpecialtyStatus::Active)
                ->orderByDesc('doctor_specialties.is_primary')
                ->orderBy('specialties.name'),
            'locations' => fn ($q) => $q
                ->where('doctor_locations.is_primary', true)
                ->with(['city:id,name,slug', 'facility:id,name,status']),
            'contacts' => fn ($q) => $q
                ->where('is_public', true)
                ->orderByDesc('is_primary'),
        ];
    }

    /**
     * Fichas publicadas del país que cumplen el criterio, sin orden ni columnas.
     *
     * @return Builder<Doctor>
     */
    private function filtered(SearchCriteria $criteria): Builder
    {
        $query = Doctor::query()
            ->where('doctors.country_id', $criteria->country->id)
            ->where('doctors.status', DoctorStatus::Active);

        if ($criteria->specialty !== null) {
            $ids = $criteria->subspecialty !== null
                ? [$criteria->subspecialty->id]
                : Specialty::where('id', $criteria->specialty->id)
                    ->orWhere('parent_id', $criteria->specialty->id)
                    ->pluck('id')
                    ->all();

            $query->whereHas('specialties', fn (Builder $q) => $q
                ->whereIn('specialties.id', $ids)
                ->where('specialties.status', SpecialtyStatus::Active));
        }

        if ($criteria->city !== null) {
            $query->whereHas('locations', fn (Builder $q) => $q
                ->where('doctor_locations.is_primary', true)
                ->where('locations.status', LocationStatus::Active)
                ->where('locations.city_id', $criteria->city->id));
        }

        if ($criteria->language !== null) {
            $query->whereHas('languages', fn (Builder $q) => $q->where('languages.id', $criteria->language->id));
        }

        if ($criteria->text !== null) {
            // Palabras completas con raíz española ("Rodriguez" encuentra "Rodríguez"),
            // o fragmentos del nombre normalizado, que usan el índice trigram.
            $query->where(fn (Builder $q) => $q
                ->whereRaw("doctors.search_vector @@ plainto_tsquery('es_unaccent', ?)", [$criteria->text])
                ->orWhere(fn (Builder $q) => DoctorSearch::apply($q, $criteria->text)));
        }

        return $query;
    }
}
