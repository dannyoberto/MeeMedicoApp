<?php

namespace App\Http\Resources\V1;

use App\Domain\Search\SearchCriteria;
use App\Http\Support\PublicUrl;
use App\Models\City;
use App\Models\Doctor;
use App\Models\Specialty;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Respuesta de GET /api/v1/{pais}/suggest. La consume la isla SearchAutocomplete y, en
 * V2, la app móvil: cada sugerencia lleva su URL pública canónica.
 *
 * @property-read array{
 *     criteria: SearchCriteria,
 *     criteriaTotal: int,
 *     specialties: Collection<int, array{specialty: Specialty, total: int}>,
 *     cities: Collection<int, array{city: City, total: int}>,
 *     doctors: Collection<int, Doctor>,
 * } $resource
 */
final class SearchSuggestionsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $criteria = $this->resource['criteria'];
        $country = $criteria->country;

        return [
            'interpretation' => $this->resource['criteriaTotal'] > 0 ? [
                'label' => self::describe($criteria),
                'meta' => self::count($this->resource['criteriaTotal']),
                'url' => PublicUrl::forCriteria($criteria),
            ] : null,
            'specialties' => $this->resource['specialties']->map(fn (array $item) => [
                'label' => $item['specialty']->name,
                'meta' => self::count($item['total']),
                'url' => PublicUrl::specialty($country, $item['specialty']),
            ])->all(),
            'cities' => $this->resource['cities']->map(fn (array $item) => [
                'label' => $item['city']->name,
                'meta' => self::count($item['total']),
                'url' => PublicUrl::forCriteria(new SearchCriteria($country, city: $item['city'])),
            ])->all(),
            'doctors' => $this->resource['doctors']->map(fn (Doctor $doctor) => [
                'label' => $doctor->displayName(),
                'meta' => collect([
                    $doctor->specialties->first()?->name,
                    $doctor->locations->first()?->city?->name,
                ])->filter()->join(' · '),
                'url' => PublicUrl::doctor($doctor),
            ])->all(),
        ];
    }

    /**
     * "Cardiología en San José · habla inglés · «Pérez»"
     */
    private static function describe(SearchCriteria $criteria): string
    {
        $what = $criteria->subspecialty?->name ?? $criteria->specialty?->name ?? 'Médicos';

        return collect([
            $what.($criteria->city ? " en {$criteria->city->name}" : ''),
            $criteria->language ? 'habla '.mb_strtolower($criteria->language->name) : null,
            $criteria->text !== null ? "«{$criteria->text}»" : null,
        ])->filter()->join(' · ');
    }

    private static function count(int $total): string
    {
        return $total === 1 ? '1 médico' : "{$total} médicos";
    }
}
