<?php

namespace App\Domain\Directory\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Búsqueda de médicos en el backoffice: tabla, búsqueda global y vincular filas de
 * importación. Cada palabra tiene que aparecer (AND), en cualquier orden.
 *
 * - Con dígitos: se busca en el colegiado ("MED-48", "4821").
 * - Sin dígitos: se normaliza con NameNormalizer y se busca en name_normalized, que ya
 *   está sin acentos ni títulos y tiene índice trigram (doctors_name_trgm_gin).
 *
 * Así "jose perez", "Perez José" y "Dra. Pérez" encuentran a "José Pérez Rojas".
 * La búsqueda pública es otra (search_vector, DATABASE.md §4).
 */
final class DoctorSearch
{
    /**
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function apply(Builder $query, string $search): Builder
    {
        foreach (self::terms($search) as [$column, $term]) {
            $query->where(
                $query->qualifyColumn($column),
                $column === 'license_number' ? 'ilike' : 'like',
                '%'.self::escapeLike($term).'%',
            );
        }

        return $query;
    }

    /**
     * @return array<int, array{0: 'license_number'|'name_normalized', 1: string}>
     */
    public static function terms(string $search): array
    {
        $terms = [];

        foreach (preg_split('/\s+/', trim($search)) ?: [] as $word) {
            if ($word === '') {
                continue;
            }

            if (preg_match('/\d/', $word)) {
                $terms[] = ['license_number', $word];

                continue;
            }

            // Un título ("Dra.") queda vacío; "Pérez-Soto" se parte en dos palabras.
            foreach (explode(' ', NameNormalizer::normalize($word)) as $token) {
                if ($token !== '') {
                    $terms[] = ['name_normalized', $token];
                }
            }
        }

        return $terms;
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
