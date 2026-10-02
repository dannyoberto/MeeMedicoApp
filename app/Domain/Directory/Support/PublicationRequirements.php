<?php

namespace App\Domain\Directory\Support;

use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Enums\LocationStatus;
use App\Domain\Directory\Enums\SpecialtyStatus;
use App\Domain\Geo\Enums\CityStatus;
use App\Models\Doctor;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * La puerta de calidad de publicación, definida en un solo lugar (AGENTS.md §3,
 * MODELO-DOMINIO.md §5.3): una ficha pública tiene al menos una especialidad, una
 * ubicación con ciudad y un contacto público. Miles de fichas vacías indexadas
 * posicionan peor que no existir.
 *
 * La usan PublishDoctorAction, los guardas que impiden dejar incompleta una ficha
 * publicada, la lista de requisitos del backoffice y, como consulta, las colas
 * "Listos para publicar" / "Incompletos". Cada requisito es una restricción que sirve
 * igual sobre la relación de una ficha (check) que en un whereHas sobre muchas
 * (whereReady): la regla se escribe una sola vez.
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
        return [
            self::SPECIALTY => $doctor->specialties()->where(self::primarySpecialty())->exists(),
            self::LOCATION => $doctor->locations()->where(self::primaryLocation())->exists(),
            self::CONTACT => $doctor->contacts()->where(self::publicContact())->exists(),
        ];
    }

    /**
     * Fichas que cumplen los tres requisitos.
     *
     * @param  Builder<Doctor>  $query
     * @return Builder<Doctor>
     */
    public static function whereReady(Builder $query): Builder
    {
        return $query
            ->whereHas('specialties', self::primarySpecialty())
            ->whereHas('locations', self::primaryLocation())
            ->whereHas('contacts', self::publicContact());
    }

    /**
     * Fichas a las que les falta al menos un requisito.
     *
     * @param  Builder<Doctor>  $query
     * @return Builder<Doctor>
     */
    public static function whereNotReady(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereDoesntHave('specialties', self::primarySpecialty())
            ->orWhereDoesntHave('locations', self::primaryLocation())
            ->orWhereDoesntHave('contacts', self::publicContact()));
    }

    private static function primarySpecialty(): Closure
    {
        return fn (Builder $q) => $q
            ->where('doctor_specialties.is_primary', true)
            ->where('specialties.status', SpecialtyStatus::Active);
    }

    /**
     * La ubicación principal decide en qué listado de ciudad aparece (MODELO-DOMINIO.md §2.4).
     */
    private static function primaryLocation(): Closure
    {
        return fn (Builder $q) => $q
            ->where('doctor_locations.is_primary', true)
            ->where('locations.status', LocationStatus::Active)
            ->whereHas('city', fn (Builder $city) => $city->where('status', CityStatus::Active));
    }

    private static function publicContact(): Closure
    {
        return fn (Builder $q) => $q->where('doctor_contacts.is_public', true);
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
