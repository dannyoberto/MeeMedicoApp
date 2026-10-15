<?php

namespace App\Domain\Search;

use App\Domain\Directory\Enums\SpecialtyStatus;
use App\Models\City;
use App\Models\Country;
use App\Models\Doctor;
use App\Models\Specialty;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Sugerencias del autocompletado mientras el paciente escribe.
 *
 * Lo primero es la interpretación de la frase completa ("cardiólogo en san jo…" ya es
 * Cardiología · San José) con su número de resultados: es el atajo al listado. Debajo,
 * especialidades y ciudades con médicos cuyo nombre o alias contiene lo escrito, y
 * médicos por nombre.
 */
final class SearchSuggestions
{
    private const LIMIT = 5;

    /**
     * Las facetas del país cambian al publicar; diez minutos de retraso en una sugerencia
     * es aceptable y ahorra dos agregaciones por pulsación.
     */
    private const FACETS_TTL = 600;

    public function __construct(
        private readonly QueryInterpreter $interpreter,
        private readonly DoctorSearchService $search,
    ) {}

    /**
     * @return array{
     *     criteria: SearchCriteria,
     *     criteriaTotal: int,
     *     specialties: Collection<int, array{specialty: Specialty, total: int}>,
     *     cities: Collection<int, array{city: City, total: int}>,
     *     doctors: Collection<int, Doctor>,
     * }
     */
    public function for(Country $country, string $query): array
    {
        $interpreted = $this->interpreter->interpret($country, $query);
        $criteria = $this->completePartial($country, $interpreted);
        $key = QueryInterpreter::key($query);

        $understood = $criteria->specialty !== null || $criteria->city !== null || $criteria->language !== null;

        return [
            'criteria' => $criteria,
            'criteriaTotal' => $understood ? $this->search->search($criteria, 1)->total() : 0,
            'specialties' => $this->specialties($country, $key),
            'cities' => $this->cities($country, $key),
            // Los médicos, por el texto tal como se escribió: "san" puede ser un nombre.
            'doctors' => $interpreted->text !== null
                ? collect($this->search->search($interpreted, self::LIMIT)->items())
                : collect(),
        ];
    }

    /**
     * Mientras se escribe, la última palabra suele estar a medias ("internista en barq").
     * Si lo que el intérprete no entendió es el comienzo de una ciudad o especialidad con
     * médicos, se completa con la que tiene más. Solo aquí: la búsqueda enviada usa la
     * frase tal cual.
     */
    private function completePartial(Country $country, SearchCriteria $criteria): SearchCriteria
    {
        $rest = $criteria->text !== null ? QueryInterpreter::key($criteria->text) : '';

        if ($rest === '') {
            return $criteria;
        }

        if ($criteria->city === null && ($city = $this->cities($country, $rest)->first())) {
            return new SearchCriteria($country, $criteria->specialty, $criteria->subspecialty, $city['city'], $criteria->language);
        }

        if ($criteria->specialty === null && ($specialty = $this->specialties($country, $rest)->first())) {
            [$top, $sub] = SearchCriteria::specialtyPair($specialty['specialty']);

            return new SearchCriteria($country, $top, $sub, $criteria->city, $criteria->language);
        }

        return $criteria;
    }

    /**
     * @return Collection<int, array{specialty: Specialty, total: int}>
     */
    private function specialties(Country $country, string $key): Collection
    {
        $totals = self::cachedTotals("search:specialty-facets:{$country->id}",
            fn () => $this->search->specialtyFacets($country)->map(fn (array $item) => [$item['specialty']->id, $item['total']]));

        $specialties = Specialty::whereIn('id', array_column($totals, 0))
            ->where('status', SpecialtyStatus::Active)
            ->with('aliases:id,specialty_id,alias_normalized')
            ->get()
            ->keyBy('id');

        return collect($totals)
            ->filter(fn (array $pair) => $specialties->has($pair[0]))
            ->map(fn (array $pair) => ['specialty' => $specialties[$pair[0]], 'total' => $pair[1]])
            ->filter(fn (array $item) => collect([$item['specialty']->name])
                ->merge($item['specialty']->aliases->pluck('alias_normalized'))
                ->contains(fn (string $name) => self::matches($name, $key)))
            ->take(self::LIMIT)
            ->values();
    }

    /**
     * @return Collection<int, array{city: City, total: int}>
     */
    private function cities(Country $country, string $key): Collection
    {
        $totals = self::cachedTotals("search:city-facets:{$country->id}",
            fn () => $this->search->cityFacets(new SearchCriteria($country))->map(fn (array $item) => [$item['city']->id, $item['total']]));

        $cities = City::whereIn('id', array_column($totals, 0))->get(['id', 'name', 'slug'])->keyBy('id');

        return collect($totals)
            ->filter(fn (array $pair) => $cities->has($pair[0]) && self::matches($cities[$pair[0]]->name, $key))
            ->take(self::LIMIT)
            ->map(fn (array $pair) => ['city' => $cities[$pair[0]], 'total' => $pair[1]])
            ->values();
    }

    /**
     * En la caché solo van escalares ([id, total] en orden): un modelo serializado depende
     * del driver y de la lista de clases que la caché acepta deserializar.
     *
     * @param  Closure(): Collection<int, array{0: string, 1: int}>  $facets
     * @return array<int, array{0: string, 1: int}>
     */
    private static function cachedTotals(string $key, Closure $facets): array
    {
        return Cache::remember($key, self::FACETS_TTL, fn () => $facets()->values()->all());
    }

    /**
     * Coincide si alguna palabra escrita empieza alguna palabra del nombre: "card" →
     * Cardiología, "jose" → San José. Cada palabra escrita tiene que encontrar su pareja.
     */
    private static function matches(string $name, string $key): bool
    {
        if ($key === '') {
            return false;
        }

        $words = explode(' ', QueryInterpreter::key($name));

        foreach (explode(' ', $key) as $typed) {
            $found = false;

            foreach ($words as $word) {
                if (str_starts_with($word, $typed)) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                return false;
            }
        }

        return true;
    }
}
