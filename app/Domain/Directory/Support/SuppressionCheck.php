<?php

namespace App\Domain\Directory\Support;

use App\Domain\Directory\Enums\ContactType;
use App\Models\Doctor;
use App\Models\DoctorSuppression;
use Illuminate\Database\Eloquent\Builder;

/**
 * ¿Pidió esta persona no aparecer? Se consulta antes de crear cualquier ficha
 * (AGENTS.md §4), al añadirle claves fuertes y al publicarla. Es la comparación
 * inversa de SuppressionMatches y la misma que hace import:match.
 *
 * Solo cuentan las supresiones vigentes: una revocada (la persona quiso volver) ya no bloquea.
 *
 * Dos niveles, como el matching del importador (§12.3):
 * - fuerte (licencia, teléfono E.164, correo): identifica a la persona → bloquea siempre;
 * - solo nombre: los homónimos son reales → bloquea salvo confirmación humana explícita.
 */
final class SuppressionCheck
{
    private const PHONE_TYPES = [ContactType::Phone, ContactType::Mobile, ContactType::Whatsapp];

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

        return self::active($countryId)
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

        return self::active($countryId)
            ->where('name_normalized', $nameNormalized)
            ->first();
    }

    /**
     * Claves fuertes de una ficha ya existente: su colegiado y sus teléfonos y correos.
     * Es la última puerta antes de publicar (PublishDoctorAction).
     */
    public static function forDoctor(Doctor $doctor): ?DoctorSuppression
    {
        $contacts = $doctor->contacts()->get(['type', 'value_normalized']);

        return self::strongMatch(
            $doctor->country_id,
            $doctor->license_number,
            $contacts->filter(fn ($c) => in_array($c->type, self::PHONE_TYPES, true))->pluck('value_normalized')->all(),
            $contacts->filter(fn ($c) => $c->type === ContactType::Email)->pluck('value_normalized')->all(),
        );
    }

    /**
     * @return Builder<DoctorSuppression>
     */
    private static function active(string $countryId): Builder
    {
        return DoctorSuppression::query()
            ->where('country_id', $countryId)
            ->whereNull('revoked_at');
    }
}
