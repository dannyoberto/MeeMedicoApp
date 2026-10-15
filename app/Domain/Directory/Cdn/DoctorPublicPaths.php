<?php

namespace App\Domain\Directory\Cdn;

use App\Domain\Directory\Enums\SpecialtyStatus;
use App\Models\Doctor;

/**
 * Rutas públicas que muestran a un médico (AGENTS.md, "Multipaís"):
 *   /{pais}/medicos/{slug}                     su ficha
 *   /{pais}                                    la home del país (médicos destacados)
 *   /{pais}/{especialidad}                     la página de cada especialidad
 *   /{pais}/{especialidad}/{ciudad}            cada listado donde aparece
 * El médico aparece en el listado de la ciudad de su ubicación PRINCIPAL
 * (MODELO-DOMINIO.md §2.4), una vez por especialidad activa de primer nivel.
 * Las URLs se construyen igual que en App\Http\Support\PublicUrl.
 *
 * Las variantes con query string (?page=, filtros, /buscar) no se pueden enumerar:
 * PublicCache les da un s-maxage corto.
 *
 * Se calculan ANTES y DESPUÉS de cada cambio: lo que deja de mostrarlo también hay que purgarlo.
 */
final class DoctorPublicPaths
{
    /**
     * @return array<int, string>
     */
    public static function for(Doctor $doctor, ?string $slug = null): array
    {
        $doctor->loadMissing('country:id,slug');
        $country = $doctor->country->slug;

        $paths = [
            "/{$country}/medicos/".($slug ?? $doctor->slug),
            // La home del país muestra médicos destacados con nombre.
            "/{$country}",
        ];

        $city = $doctor->locations()
            ->wherePivot('is_primary', true)
            ->with('city:id,slug')
            ->first()?->city?->slug;

        // Una subespecialidad no tiene páginas propias: aparece en las de su padre
        // (?sub= es una variante de esa misma URL, ARQUITECTURA.md §12 #2).
        $specialties = $doctor->specialties()
            ->where('specialties.status', SpecialtyStatus::Active)
            ->with('parent:id,slug')
            ->get(['specialties.id', 'specialties.slug', 'specialties.parent_id'])
            ->map(fn ($specialty) => $specialty->parent?->slug ?? $specialty->slug)
            ->unique();

        foreach ($specialties as $specialty) {
            // La página de la especialidad muestra sus primeros especialistas.
            $paths[] = "/{$country}/{$specialty}";

            if ($city) {
                $paths[] = "/{$country}/{$specialty}/{$city}";
            }
        }

        return array_values(array_unique($paths));
    }
}
