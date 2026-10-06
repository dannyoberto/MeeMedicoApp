<?php

namespace App\Domain\Directory\Support;

use App\Models\SlugRedirect;
use Illuminate\Support\Str;

/**
 * Slug a partir de un nombre, único en su país, para establecimientos y aseguradoras:
 * "Hospital Clínica Bíblica" → hospital-clinica-biblica → …-2. Los médicos tienen su
 * propia secuencia con la especialidad (DoctorSlugGenerator).
 *
 * Libre = válido según SlugRules y no usado como slug ANTIGUO de otro registro del
 * mismo tipo: la URL ya indexada de otro hospital no puede pasar a mostrar este.
 */
final class EntitySlugGenerator
{
    private const BASE_MAX = 150;

    /**
     * @param  class-string  $modelClass
     */
    public static function generate(string $modelClass, string $name, string $countryId, ?string $ignoreId = null): string
    {
        // Un nombre sin letras ni números ("—") cae en el tipo: establecimiento, aseguradora.
        $base = rtrim(Str::limit(Str::slug($name), self::BASE_MAX, ''), '-')
            ?: Str::slug(SlugRules::entityType($modelClass)->getLabel());

        for ($n = 1; ; $n++) {
            $candidate = $n === 1 ? $base : "{$base}-{$n}";
            if (self::isAvailable($modelClass, $candidate, $countryId, $ignoreId)) {
                return $candidate;
            }
        }
    }

    /**
     * @param  class-string  $modelClass
     */
    private static function isAvailable(string $modelClass, string $slug, string $countryId, ?string $ignoreId): bool
    {
        if (SlugRules::violation($modelClass, $slug, $countryId, $ignoreId) !== null) {
            return false;
        }

        return ! SlugRedirect::query()
            ->where('entity_type', SlugRules::entityType($modelClass))
            ->where('country_id', $countryId)
            ->where('old_slug', $slug)
            ->when($ignoreId, fn ($q, $id) => $q->where('entity_id', '<>', $id))
            ->exists();
    }
}
