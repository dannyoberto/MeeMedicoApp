<?php

namespace App\Domain\Directory\Support;

use App\Models\DoctorSuppression;

/**
 * ¿Pidió esta persona no aparecer? Se consulta antes de crear cualquier ficha
 * (AGENTS.md §4). Es la comparación inversa de SuppressionMatches y la misma que
 * hará import:match.
 *
 * Dos niveles, como el matching del importador (§12.3):
 * - fuerte (licencia, teléfono E.164, correo): identifica a la persona → bloquea siempre;
 * - solo nombre: los homónimos son reales → bloquea salvo confirmación humana explícita.
 */
final class SuppressionCheck
{
    /**
     * @param  array<int, string>  $phonesE164
     * @param  array<int, string>  $emails
     */
    public static function strongMatch(string $countryId, ?string $license, array $phonesE164 = [], array $emails = []): ?DoctorSuppression
    {
        $emails = array_values(array_filter(array_map(fn (string $e) => mb_strtolower(trim($e)), $emails)));

        if (! $license && ! $phonesE164 && ! $emails) {
            return null;
        }

        return DoctorSuppression::query()
            ->where('country_id', $countryId)
            ->where(function ($q) use ($license, $phonesE164, $emails) {
                $q->when($license, fn ($q, $v) => $q->orWhere('license_number', $v))
                    ->when($phonesE164, fn ($q, $v) => $q->orWhereIn('phone_normalized', $v))
                    ->when($emails, fn ($q, $v) => $q->orWhereIn('email_normalized', $v));
            })
            ->first();
    }

    public static function nameMatch(string $countryId, ?string $nameNormalized): ?DoctorSuppression
    {
        if (! $nameNormalized) {
            return null;
        }

        return DoctorSuppression::query()
            ->where('country_id', $countryId)
            ->where('name_normalized', $nameNormalized)
            ->first();
    }
}
