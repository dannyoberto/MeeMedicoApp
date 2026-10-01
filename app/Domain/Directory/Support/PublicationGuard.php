<?php

namespace App\Domain\Directory\Support;

use App\Domain\Directory\Exceptions\DirectoryRuleException;
use App\Models\Doctor;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Una ficha publicada nunca queda incompleta (decisión de la Etapa 3: se bloquea,
 * no se despublica sola). Aplica el cambio, comprueba los requisitos y, si la ficha
 * publicada dejaría de cumplirlos, revierte y explica qué hacer.
 */
final class PublicationGuard
{
    /**
     * @template T
     *
     * @param  Closure(): T  $change
     * @return T
     *
     * @throws DirectoryRuleException
     */
    public static function run(Doctor $doctor, Closure $change): mixed
    {
        return DB::transaction(function () use ($doctor, $change) {
            $result = $change();

            if (PublicationRequirements::isPublished($doctor->fresh()) && $missing = PublicationRequirements::missing($doctor)) {
                throw new DirectoryRuleException(
                    'Esta ficha está publicada: añade otra antes de quitar esta, o despublícala primero.',
                    array_values($missing),
                );
            }

            return $result;
        });
    }
}
