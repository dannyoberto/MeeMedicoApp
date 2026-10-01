<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Support\NameNormalizer;
use App\Domain\Directory\Support\PhoneNormalizer;
use App\Models\Country;
use App\Models\DoctorSuppression;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra que una persona pidió no aparecer en el directorio (DATABASE.md §14.1).
 * El importador la consulta antes de crear cualquier ficha, así la baja sobrevive
 * al siguiente lote. Es un registro legal: no se edita ni se borra.
 */
class CreateSuppressionAction
{
    /**
     * @throws ValidationException (claves: phone, email, keys)
     */
    public function execute(
        Country $country,
        ?string $licenseNumber,
        ?string $email,
        ?string $phone,
        ?string $name,
        ?string $reason,
        CarbonInterface $requestedAt,
        ?User $actor,
    ): DoctorSuppression {
        $license = filled($licenseNumber) ? trim($licenseNumber) : null;
        $emailNormalized = filled($email) ? mb_strtolower(trim($email)) : null;
        $nameNormalized = filled($name) ? (NameNormalizer::normalize($name) ?: null) : null;

        $phoneNormalized = null;
        if (filled($phone)) {
            $phoneNormalized = PhoneNormalizer::toE164($phone, $country->code)
                ?? throw ValidationException::withMessages([
                    'phone' => "No es un teléfono válido de {$country->name}.",
                ]);
        }

        if ($emailNormalized !== null && ! filter_var($emailNormalized, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'No es un correo válido.']);
        }

        if (! $license && ! $emailNormalized && ! $phoneNormalized && ! $nameNormalized) {
            throw ValidationException::withMessages([
                'keys' => 'Indica al menos una clave: licencia, correo, teléfono o nombre.',
            ]);
        }

        return DB::transaction(function () use ($country, $license, $emailNormalized, $phoneNormalized, $nameNormalized, $reason, $requestedAt, $actor) {
            $suppression = DoctorSuppression::create([
                'country_id' => $country->getKey(),
                'license_number' => $license,
                'email_normalized' => $emailNormalized,
                'phone_normalized' => $phoneNormalized,
                'name_normalized' => $nameNormalized,
                'reason' => $reason,
                'requested_at' => $requestedAt,
                'created_by_user_id' => $actor?->getKey(),
            ]);

            activity()
                ->performedOn($suppression)
                ->causedBy($actor)
                ->event('suppression.created')
                ->log('suppression.created');

            return $suppression;
        });
    }
}
