<?php

namespace App\Domain\Directory\Support;

use App\Domain\Geo\Support\Normalize;

/**
 * Clave de nombre de un médico: doctors.name_normalized, import_rows.n_name_key y
 * doctor_suppressions.name_normalized (DATABASE.md §9.1). La escribe PHP, nunca SQL.
 *
 * Minúsculas, sin acentos, sin títulos ni puntuación, y tokens ordenados: así
 * "Dra. María José Pérez" y "PEREZ, Maria Jose" producen la misma clave.
 */
final class NameNormalizer
{
    /**
     * Títulos que no forman parte del nombre. Se comparan ya sin puntos.
     */
    public const TITLES = [
        'dr', 'dra', 'doctor', 'doctora',
        'lic', 'licda', 'licdo', 'licenciada', 'licenciado',
        'md', 'msc', 'phd',
    ];

    public static function normalize(string $name): string
    {
        $text = preg_replace('/[^a-z0-9\s]/', ' ', Normalize::text($name)) ?? '';

        $tokens = array_values(array_filter(
            preg_split('/\s+/', $text) ?: [],
            fn (string $token) => $token !== '' && ! in_array($token, self::TITLES, true),
        ));

        sort($tokens, SORT_STRING);

        return implode(' ', $tokens);
    }
}
