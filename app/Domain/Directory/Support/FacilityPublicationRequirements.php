<?php

namespace App\Domain\Directory\Support;

use App\Domain\Directory\Enums\FacilityStatus;
use App\Domain\Directory\Enums\LocationStatus;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use App\Models\Facility;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Puerta de calidad del establecimiento (DATABASE.md §9.10): al menos una sede activa
 * y un contacto público. Mientras no exista la landing, "activo" significa "listo para
 * publicar"; así el conjunto publicable ya estará depurado el día que se construya.
 *
 * A diferencia del médico no exige ciudad activa: el establecimiento no genera listados
 * por ciudad, y su sede sigue siendo una dirección válida.
 */
final class FacilityPublicationRequirements
{
    public const LOCATION = 'location';

    public const CONTACT = 'contact';

    public const LABELS = [
        self::LOCATION => 'Una sede activa',
        self::CONTACT => 'Un contacto público',
    ];

    /**
     * @return array<string, bool> requisito => cumplido
     */
    public static function check(Facility $facility): array
    {
        return [
            self::LOCATION => $facility->locations()->where('status', LocationStatus::Active)->exists(),
            self::CONTACT => $facility->contacts()->where('is_public', true)->exists(),
        ];
    }

    /**
     * @return array<string, string> requisitos pendientes => descripción
     */
    public static function missing(Facility $facility): array
    {
        return array_intersect_key(self::LABELS, array_filter(self::check($facility), fn (bool $ok) => ! $ok));
    }

    /**
     * Un establecimiento activo nunca queda incompleto: aplica el cambio y, si lo dejaría
     * sin sede activa o sin contacto público, revierte y explica qué hacer (como
     * PublicationGuard con las fichas de médicos).
     *
     * @template T
     *
     * @param  Closure(): T  $change
     * @return T
     *
     * @throws DirectoryRuleException
     */
    public static function guard(Facility $facility, Closure $change): mixed
    {
        return DB::transaction(function () use ($facility, $change) {
            $result = $change();

            if ($facility->fresh()->status === FacilityStatus::Active && $missing = self::missing($facility)) {
                throw new DirectoryRuleException(
                    'Este establecimiento está activo: añade otra antes de quitar esta, o desactívalo primero.',
                    array_values($missing),
                );
            }

            return $result;
        });
    }
}
