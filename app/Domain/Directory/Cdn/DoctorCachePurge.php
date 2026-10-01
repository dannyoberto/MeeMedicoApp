<?php

namespace App\Domain\Directory\Cdn;

use App\Domain\Directory\Support\PublicationRequirements;
use App\Models\Doctor;
use Closure;

/**
 * Ejecuta un cambio sobre un médico y purga lo que la CDN pudiera tener en caché:
 * las rutas que lo mostraban ANTES (si estaba publicado) y las que lo muestran
 * DESPUÉS (si lo está). Un borrador no tiene páginas cacheadas: no se purga nada.
 */
final class DoctorCachePurge
{
    /**
     * @template T
     *
     * @param  Closure(): T  $change
     * @return T
     */
    public static function around(Doctor $doctor, Closure $change): mixed
    {
        $before = PublicationRequirements::isPublished($doctor) ? DoctorPublicPaths::for($doctor) : [];

        $result = $change();

        $doctor->refresh();
        $after = PublicationRequirements::isPublished($doctor) ? DoctorPublicPaths::for($doctor) : [];

        $paths = array_values(array_unique([...$before, ...$after]));
        if ($paths !== []) {
            PurgeCdnPaths::dispatch($paths);
        }

        return $result;
    }
}
