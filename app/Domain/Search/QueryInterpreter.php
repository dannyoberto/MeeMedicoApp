<?php

namespace App\Domain\Search;

use App\Domain\Directory\Enums\LanguageStatus;
use App\Domain\Directory\Enums\SpecialtyStatus;
use App\Domain\Geo\Enums\CityStatus;
use App\Domain\Geo\Support\Normalize;
use App\Models\City;
use App\Models\CityAlias;
use App\Models\Country;
use App\Models\Language;
use App\Models\Specialty;

/**
 * Convierte lo que escribe el paciente en criterios de búsqueda:
 *
 *   "pediatra en Heredia que hable inglés"  →  Pediatría · Heredia · inglés
 *   "cardiólogos san josé"                  →  Cardiología · San José
 *   "Dra. Pérez dermatología"               →  Dermatología · texto "Pérez"
 *
 * Es determinista y sin IA: compara grupos de palabras (de 5 a 1) con los nombres y
 * alias de los catálogos, en este orden: especialidad, ciudad del país, idioma. Lo que
 * sobra, sin palabras vacías, se busca como nombre del médico.
 *
 * Los alias 'seo' de specialty_aliases ("pediatra", "cardióloga") son los que permiten
 * escribir como habla la gente (DATABASE.md §8.2).
 */
final class QueryInterpreter
{
    private const MAX_WORDS = 5;

    /**
     * Palabras que no aportan criterio. Español también: es el idioma por defecto en los
     * cuatro países y filtrar por él dejaría fuera a quien no lo tiene registrado.
     */
    private const STOPWORDS = [
        'a', 'al', 'con', 'de', 'del', 'el', 'en', 'la', 'las', 'los', 'para', 'por', 'que', 'y',
        'un', 'una', 'unos', 'unas', 'cerca', 'busco', 'buscar', 'necesito', 'quiero',
        'medico', 'medicos', 'medica', 'medicas', 'doctor', 'doctora', 'doctores', 'doctoras',
        'dr', 'dra', 'especialista', 'especialistas',
        'hable', 'habla', 'hablen', 'hablan', 'idioma', 'espanol', 'castellano',
    ];

    public function interpret(Country $country, string $query, int $page = 1): SearchCriteria
    {
        $words = self::words($query);

        $specialty = $this->take($words, $this->specialties());
        $city = $this->take($words, $this->cities($country));
        $language = $this->take($words, $this->languages());

        $rest = array_filter($words, fn (array $w) => ! in_array($w['key'], self::STOPWORDS, true));
        $text = $rest === [] ? null : implode(' ', array_column($rest, 'raw'));

        [$specialty, $subspecialty] = SearchCriteria::specialtyPair($specialty);

        return new SearchCriteria($country, $specialty, $subspecialty, $city, $language, $text, max(1, $page));
    }

    /**
     * Cada palabra con su forma original (para mostrarla) y su clave de comparación:
     * minúsculas, sin acentos ni signos. "Pérez-Soto" son dos palabras.
     *
     * @return array<int, array{raw: string, key: string}>
     */
    public static function words(string $text): array
    {
        $words = [];

        foreach (preg_split('/[^\p{L}\p{N}]+/u', $text) ?: [] as $raw) {
            $key = self::key($raw);

            if ($key !== '') {
                $words[] = ['raw' => $raw, 'key' => $key];
            }
        }

        return $words;
    }

    /**
     * Clave de comparación de un texto completo: la misma que producen words().
     */
    public static function key(string $text): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Normalize::text($text)) ?? '');
    }

    /**
     * Busca el grupo de palabras más largo que coincide con el mapa, lo quita de $words
     * y devuelve su valor. "cardiologos" también coincide con "cardiologo".
     *
     * @template T
     *
     * @param  array<int, array{raw: string, key: string}>  $words
     * @param  array<string, T>  $map
     * @return T|null
     */
    private function take(array &$words, array $map): mixed
    {
        $count = count($words);

        for ($size = min(self::MAX_WORDS, $count); $size >= 1; $size--) {
            for ($start = 0; $start + $size <= $count; $start++) {
                $keys = array_column(array_slice($words, $start, $size), 'key');

                $match = $map[implode(' ', $keys)]
                    ?? $map[implode(' ', array_map(self::singular(...), $keys))]
                    ?? null;

                if ($match !== null) {
                    array_splice($words, $start, $size);

                    return $match;
                }
            }
        }

        return null;
    }

    private static function singular(string $word): string
    {
        return strlen($word) > 3 && str_ends_with($word, 's') ? substr($word, 0, -1) : $word;
    }

    /**
     * @return array<string, Specialty>
     */
    private function specialties(): array
    {
        $map = [];

        $specialties = Specialty::where('status', SpecialtyStatus::Active)
            ->with(['aliases', 'parent'])
            ->orderBy('name')
            ->get();

        foreach ($specialties as $specialty) {
            foreach ([$specialty->name, str_replace('-', ' ', $specialty->slug)] as $name) {
                $map[self::key($name)] ??= $specialty;
            }

            foreach ($specialty->aliases as $alias) {
                $map[self::key($alias->alias_normalized)] ??= $specialty;
            }
        }

        return $map;
    }

    /**
     * Solo ciudades del país. Si dos ciudades comparten nombre, gana la primera por
     * orden alfabético de región: es una limitación conocida, el paciente puede elegir
     * la ciudad en el listado.
     *
     * @return array<string, City>
     */
    private function cities(Country $country): array
    {
        $map = [];

        $cities = City::where('country_id', $country->id)
            ->where('status', CityStatus::Active)
            ->orderBy('region_id')
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        foreach ($cities as $city) {
            $map[self::key($city->name)] ??= $city;
        }

        $aliases = CityAlias::where('country_id', $country->id)
            ->whereIn('city_id', $cities->keys())
            ->get();

        foreach ($aliases as $alias) {
            $map[self::key($alias->alias_normalized)] ??= $cities[$alias->city_id];
        }

        return $map;
    }

    /**
     * Por nombre ("inglés"), nunca por código: "en" es una preposición.
     *
     * @return array<string, Language>
     */
    private function languages(): array
    {
        return Language::where('status', LanguageStatus::Active)
            ->where('code', '!=', 'es')
            ->get()
            ->mapWithKeys(fn (Language $language) => [self::key($language->name) => $language])
            ->all();
    }
}
