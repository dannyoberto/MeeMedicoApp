<?php

namespace App\Domain\Directory\Support;

use App\Domain\Geo\Support\Normalize;
use Illuminate\Database\Eloquent\Builder;

/**
 * Búsqueda de ubicaciones en el backoffice, sin acentos: "clinica biblica" encuentra
 * "Hospital Clínica Bíblica". La dirección se compara con address_normalized (que ya
 * escribe SaveLocationAction) y el nombre del lugar, sin acentos en la consulta.
 */
final class LocationSearch
{
    /**
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function apply(Builder $query, string $search): Builder
    {
        $term = Normalize::text($search);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        return $query->where(fn (Builder $q) => $q
            ->where($q->qualifyColumn('address_normalized'), 'like', $like)
            ->orWhereRaw('immutable_unaccent(lower('.$q->qualifyColumn('name').')) like ?', [$like]));
    }
}
