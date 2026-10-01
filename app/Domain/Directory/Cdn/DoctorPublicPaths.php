<?php

namespace App\Domain\Directory\Cdn;

use App\Domain\Directory\Enums\SpecialtyStatus;
use App\Models\Doctor;

/**
 * Rutas públicas que muestran a un médico (AGENTS.md, "Multipaís"):
 *   /{pais}/medicos/{slug}                     su ficha
 *   /{pais}/{especialidad}/{ciudad}            cada listado donde aparece
 * El médico aparece en el listado de la ciudad de su ubicación PRINCIPAL
 * (MODELO-DOMINIO.md §2.4), una vez por especialidad activa.
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

        $paths = ["/{$country}/medicos/".($slug ?? $doctor->slug)];

        $city = $doctor->locations()
            ->wherePivot('is_primary', true)
            ->with('city:id,slug')
            ->first()?->city?->slug;

        if ($city) {
            $specialties = $doctor->specialties()
                ->where('specialties.status', SpecialtyStatus::Active)
                ->pluck('specialties.slug');

            foreach ($specialties as $specialty) {
                $paths[] = "/{$country}/{$specialty}/{$city}";
            }
        }

        return $paths;
    }
}
