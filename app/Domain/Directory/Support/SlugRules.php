<?php

namespace App\Domain\Directory\Support;

use App\Domain\Directory\Enums\SlugRedirectEntity;
use App\Models\City;
use App\Models\Country;
use App\Models\Doctor;
use App\Models\Region;
use App\Models\Specialty;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Reglas de un slug público: formato, reservados y unicidad en su ámbito.
 * Las usan UpdateSlugAction y los formularios de creación, para que ambos caminos
 * rechacen exactamente lo mismo.
 */
final class SlugRules
{
    /**
     * Tipo de redirect y modelo de cada entidad con slug editable.
     */
    private const ENTITIES = [
        Doctor::class => SlugRedirectEntity::Doctor,
        Specialty::class => SlugRedirectEntity::Specialty,
        Region::class => SlugRedirectEntity::Region,
        City::class => SlugRedirectEntity::City,
    ];

    public static function entityType(Model|string $model): SlugRedirectEntity
    {
        $class = is_string($model) ? $model : $model::class;

        return self::ENTITIES[$class]
            ?? throw new InvalidArgumentException("{$class} no tiene slug gestionado por SlugRules.");
    }

    /**
     * Ámbito de unicidad: especialidades son globales; regiones y ciudades, por país.
     */
    public static function countryScope(SlugRedirectEntity $type, ?string $countryId): ?string
    {
        return $type === SlugRedirectEntity::Specialty ? null : $countryId;
    }

    /**
     * Devuelve el motivo del rechazo, o null si el slug es válido.
     */
    public static function violation(string $modelClass, string $slug, ?string $countryId, ?string $ignoreId = null): ?string
    {
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return 'Solo minúsculas sin acentos, números y guiones simples (ej.: san-jose).';
        }

        // doctors.slug es varchar(220); el resto de entidades, varchar(180).
        $max = $modelClass === Doctor::class ? 220 : 180;
        if (strlen($slug) > $max) {
            return "Máximo {$max} caracteres.";
        }

        if (self::isReserved($slug)) {
            return "\"{$slug}\" está reservado por las rutas públicas.";
        }

        $type = self::entityType($modelClass);

        $taken = $modelClass::query()
            ->where('slug', $slug)
            ->when(self::countryScope($type, $countryId), fn ($q, $country) => $q->where('country_id', $country))
            ->when($ignoreId, fn ($q, $id) => $q->whereKeyNot($id))
            ->exists();

        return $taken ? 'Ya existe otro registro con ese slug.' : null;
    }

    public static function isReserved(string $slug): bool
    {
        return in_array($slug, config('meemedico.reserved_slugs'), true)
            || Country::where('slug', $slug)->exists();
    }
}
