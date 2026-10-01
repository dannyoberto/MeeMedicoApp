<?php

namespace App\Domain\Geo\Support;

use Illuminate\Support\Str;

/**
 * Normalización de texto libre para las columnas *_normalized (DATABASE.md §3.6):
 * minúsculas, sin acentos, espacios colapsados. La escribe la aplicación, nunca el motor.
 *
 * La comparten el backoffice (alias) y el importador, para que "San José Centro"
 * escrito a mano y el mismo texto en un lote produzcan exactamente la misma clave.
 */
final class Normalize
{
    public static function text(string $value): string
    {
        $ascii = Str::ascii(mb_strtolower($value));

        return trim(preg_replace('/\s+/', ' ', $ascii) ?? '');
    }
}
