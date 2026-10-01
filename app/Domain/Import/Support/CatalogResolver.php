<?php

namespace App\Domain\Import\Support;

use App\Domain\Directory\Enums\DoctorGender;
use App\Domain\Directory\Enums\LanguageStatus;
use App\Domain\Directory\Enums\LocationType;
use App\Domain\Directory\Enums\SpecialtyStatus;
use App\Domain\Geo\Enums\CityStatus;
use App\Domain\Geo\Support\Normalize;
use App\Models\City;
use App\Models\CityAlias;
use App\Models\Country;
use App\Models\Language;
use App\Models\Specialty;
use App\Models\SpecialtyAlias;
use Illuminate\Support\Collection;

/**
 * Traduce texto libre del archivo a entradas del catálogo, con el catálogo y los alias
 * precargados una vez por lote. Nunca adivina: si no hay una correspondencia única,
 * devuelve null y la fila va a revisión (§12.3: "nunca se inventa un valor").
 */
final class CatalogResolver
{
    /** @var Collection<int, object{id: string, name: string, region: string}> */
    private Collection $cities;

    /** @var array<string, string> alias normalizado => city_id */
    private array $cityAliases;

    /** @var array<string, string> nombre o alias normalizado => specialty_id */
    private array $specialties;

    /** @var array<string, string> nombre o código normalizado => language_id */
    private array $languages;

    public function __construct(public readonly Country $country)
    {
        $this->cities = City::query()
            ->where('cities.country_id', $country->getKey())
            ->where('cities.status', CityStatus::Active)
            ->join('regions', 'regions.id', '=', 'cities.region_id')
            ->get(['cities.id', 'cities.name', 'regions.name as region_name'])
            ->map(fn ($c) => (object) ['id' => $c->id, 'name' => Normalize::text($c->name), 'region' => Normalize::text($c->region_name)]);

        $this->cityAliases = CityAlias::where('country_id', $country->getKey())
            ->whereHas('city', fn ($q) => $q->where('status', CityStatus::Active))
            ->pluck('city_id', 'alias_normalized')->all();

        $active = Specialty::where('status', SpecialtyStatus::Active);
        $this->specialties = [
            ...SpecialtyAlias::whereIn('specialty_id', (clone $active)->select('id'))->pluck('specialty_id', 'alias_normalized')->all(),
            ...(clone $active)->get(['id', 'name'])->mapWithKeys(fn ($s) => [Normalize::text($s->name) => $s->id])->all(),
        ];

        $this->languages = Language::where('status', LanguageStatus::Active)->get(['id', 'name', 'code'])
            ->flatMap(fn ($l) => [Normalize::text($l->name) => $l->id, Normalize::text($l->code) => $l->id])->all();
    }

    /**
     * @return array{0: ?string, 1: ?string} [city_id, código de incidencia si no se resolvió]
     */
    public function city(?string $city, ?string $region): array
    {
        if ($city === null) {
            return [null, RowIssue::MISSING];
        }

        $name = Normalize::text($city);
        $candidates = $this->cities->where('name', $name);

        if ($region !== null && $candidates->count() > 1) {
            $candidates = $candidates->where('region', Normalize::text($region));
        }

        if ($candidates->count() === 1) {
            return [$candidates->first()->id, null];
        }

        if ($candidates->count() > 1) {
            return [null, RowIssue::AMBIGUOUS_CITY];
        }

        return isset($this->cityAliases[$name]) ? [$this->cityAliases[$name], null] : [null, RowIssue::UNKNOWN_CITY];
    }

    public function specialty(string $text): ?string
    {
        return $this->specialties[Normalize::text($text)] ?? null;
    }

    public function language(string $text): ?string
    {
        return $this->languages[Normalize::text($text)] ?? null;
    }

    public static function gender(?string $label): ?DoctorGender
    {
        return $label === null ? null : collect(DoctorGender::cases())
            ->first(fn (DoctorGender $g) => Normalize::text($g->getLabel()) === Normalize::text($label) || $g->value === Normalize::text($label));
    }

    public static function locationType(?string $label): ?LocationType
    {
        return $label === null ? LocationType::Office : collect(LocationType::cases())
            ->first(fn (LocationType $t) => Normalize::text($t->getLabel()) === Normalize::text($label) || $t->value === Normalize::text($label));
    }

    /**
     * "Español; Inglés" → ['Español', 'Inglés']
     *
     * @return array<int, string>
     */
    public static function splitList(?string $value): array
    {
        return $value === null ? [] : array_values(array_filter(array_map('trim', preg_split('/[;,\/]+/', $value) ?: [])));
    }
}
