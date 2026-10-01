<?php

namespace App\Domain\Directory\Support;

use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Enums\LocationStatus;
use App\Domain\Directory\Enums\SpecialtyStatus;
use App\Domain\Geo\Enums\CityStatus;
use App\Models\Doctor;

/**
 * La puerta de calidad de publicación, definida en un solo lugar (AGENTS.md §3,
 * MODELO-DOMINIO.md §5.3): una ficha pública tiene al menos una especialidad, una
 * ubicación con ciudad y un contacto público. Miles de fichas vacías indexadas
 * posicionan peor que no existir.
 *
 * La usan PublishDoctorAction, los guardas que impiden dejar incompleta una ficha
 * publicada y la lista de requisitos del backoffice.
 */
final class PublicationRequirements
{
    public const SPECIALTY = 'specialty';

    public const LOCATION = 'location';

    public const CONTACT = 'contact';

    public const LABELS = [
        self::SPECIALTY => 'Una especialidad activa marcada como principal',
        self::LOCATION => 'Una ubicación activa, con ciudad activa, marcada como principal',
        self::CONTACT => 'Un contacto público',
    ];

    /**
     * @return array<string, bool> requisito => cumplido
     */
    public static function check(Doctor $doctor): array
    {
        $primarySpecialty = $doctor->specialties()
            ->wherePivot('is_primary', true)
            ->where('specialties.status', SpecialtyStatus::Active)
            ->exists();

        // La ubicación principal decide en qué listado de ciudad aparece (MODELO-DOMINIO.md §2.4).
        $primaryLocation = $doctor->locations()
            ->wherePivot('is_primary', true)
            ->where('locations.status', LocationStatus::Active)
            ->whereHas('city', fn ($q) => $q->where('status', CityStatus::Active))
            ->exists();

        $publicContact = $doctor->contacts()->where('is_public', true)->exists();

        return [
            self::SPECIALTY => $primarySpecialty,
            self::LOCATION => $primaryLocation,
            self::CONTACT => $publicContact,
        ];
    }

    /**
     * @return array<string, string> requisitos pendientes => descripción
     */
    public static function missing(Doctor $doctor): array
    {
        return array_intersect_key(self::LABELS, array_filter(self::check($doctor), fn (bool $ok) => ! $ok));
    }

    public static function isPublished(Doctor $doctor): bool
    {
        return $doctor->status === DoctorStatus::Active;
    }
}
