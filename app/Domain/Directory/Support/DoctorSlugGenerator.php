<?php

namespace App\Domain\Directory\Support;

use App\Domain\Directory\Enums\SlugRedirectEntity;
use App\Models\Doctor;
use App\Models\SlugRedirect;
use App\Models\Specialty;
use Illuminate\Support\Str;

/**
 * Slug de la URL /{pais}/medicos/{slug}, único por país (MODELO-DOMINIO.md §7).
 *
 * Secuencia ante homónimos (decisión de la Etapa 3): juan-perez →
 * juan-perez-cardiologia (especialidad principal) → juan-perez-cardiologia-2 …
 * La especialidad aporta palabras con valor SEO donde un número no aporta nada.
 */
final class DoctorSlugGenerator
{
    private const BASE_MAX = 150;

    public static function generate(string $countryId, string $firstName, string $lastName, ?Specialty $primarySpecialty = null, ?string $ignoreDoctorId = null): string
    {
        $base = self::base("{$firstName} {$lastName}");

        $candidates = [$base];
        $stem = $base;
        if ($primarySpecialty) {
            $stem = "{$base}-{$primarySpecialty->slug}";
            $candidates[] = $stem;
        }

        foreach ($candidates as $candidate) {
            if (self::isAvailable($candidate, $countryId, $ignoreDoctorId)) {
                return $candidate;
            }
        }

        for ($n = 2; ; $n++) {
            $candidate = "{$stem}-{$n}";
            if (self::isAvailable($candidate, $countryId, $ignoreDoctorId)) {
                return $candidate;
            }
        }
    }

    /**
     * "Dra. María José Pérez" → "maria-jose-perez". Sin títulos: no forman parte del nombre.
     */
    public static function base(string $fullName): string
    {
        $tokens = array_filter(
            preg_split('/[\s.,]+/', Str::lower(Str::ascii($fullName))) ?: [],
            fn (string $t) => $t !== '' && ! in_array($t, NameNormalizer::TITLES, true),
        );

        $slug = Str::limit(Str::slug(implode(' ', $tokens)), self::BASE_MAX, '');

        return rtrim($slug, '-') ?: 'medico';
    }

    /**
     * Libre = válido según SlugRules (formato, reservados, único en el país) y no
     * usado como slug ANTIGUO de otro médico: si no, la URL ya indexada de esa otra
     * persona pasaría a mostrar esta ficha.
     */
    private static function isAvailable(string $slug, string $countryId, ?string $ignoreDoctorId): bool
    {
        if (SlugRules::violation(Doctor::class, $slug, $countryId, $ignoreDoctorId) !== null) {
            return false;
        }

        return ! SlugRedirect::query()
            ->where('entity_type', SlugRedirectEntity::Doctor)
            ->where('country_id', $countryId)
            ->where('old_slug', $slug)
            ->when($ignoreDoctorId, fn ($q, $id) => $q->where('entity_id', '<>', $id))
            ->exists();
    }
}
