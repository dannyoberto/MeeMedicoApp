<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Cdn\DoctorCachePurge;
use App\Domain\Directory\Enums\LicenseSource;
use App\Domain\Directory\Enums\VerificationStatus;
use App\Domain\Directory\Support\NameNormalizer;
use App\Domain\Directory\Support\SuppressionCheck;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Edición de datos y perfil. NO cambia el slug: eso es UpdateSlugAction, que deja el 301.
 * Corregir el nombre no altera la URL ya indexada.
 */
class UpdateDoctorAction
{
    private const DOCTOR_FIELDS = ['first_name', 'last_name', 'professional_name', 'gender', 'license_number', 'license_source'];

    private const PROFILE_FIELDS = ['headline', 'bio', 'education', 'experience'];

    /**
     * @param  array<string, mixed>  $data  campos de doctors y de doctor_profiles
     *
     * @throws ValidationException (clave: license_number)
     */
    public function execute(Doctor $doctor, array $data, ?User $actor): Doctor
    {
        $fields = array_intersect_key($data, array_flip(self::DOCTOR_FIELDS));
        $profile = array_intersect_key($data, array_flip(self::PROFILE_FIELDS));

        if (array_key_exists('license_number', $fields)) {
            $fields['license_number'] = filled($fields['license_number']) ? trim($fields['license_number']) : null;

            $taken = $fields['license_number'] && Doctor::where('country_id', $doctor->country_id)
                ->where('license_number', $fields['license_number'])
                ->whereKeyNot($doctor->getKey())
                ->exists();
            if ($taken) {
                throw ValidationException::withMessages(['license_number' => 'Ya existe otra ficha con ese número de colegiado en este país.']);
            }

            if ($fields['license_number'] !== $doctor->license_number
                && $suppression = SuppressionCheck::strongMatch($doctor->country_id, $fields['license_number'])) {
                throw ValidationException::withMessages(['license_number' => 'Ese colegiado es de una persona que pidió no aparecer en el directorio (supresión del '
                    .$suppression->requested_at->format('d/m/Y').').']);
            }

            if ($fields['license_number'] === null) {
                $fields['license_source'] = null;
            } elseif (blank($fields['license_source'] ?? $doctor->license_source)) {
                $fields['license_source'] = LicenseSource::Admin->value;
            }
        }

        return DoctorCachePurge::around($doctor, fn () => DB::transaction(function () use ($doctor, $fields, $profile, $actor) {
            $doctor->fill($fields);
            $doctor->forceFill(['name_normalized' => NameNormalizer::normalize("{$doctor->first_name} {$doctor->last_name}")]);

            // Lo verificado era ESTE número de colegiado: si cambia, la insignia no se
            // transfiere (MODELO-IDENTIDAD.md §6). Corregir el nombre no la afecta.
            $licenseChanged = $doctor->isDirty('license_number');
            if ($licenseChanged && $doctor->verification_status === VerificationStatus::Verified) {
                $doctor->forceFill([
                    'verification_status' => VerificationStatus::Unverified,
                    'verification_source' => null,
                    'verified_at' => null,
                    'verified_by_user_id' => null,
                    'license_verified_at' => null,
                ]);
            }

            $changes = collect($doctor->getDirty())->except('updated_at')->keys()->all();
            // Antes y después de cada campo, para que la auditoría permita deshacer a mano.
            // name_normalized se deriva del nombre: no aporta nada a quien lee el historial.
            $values = self::values($doctor, except: ['updated_at', 'name_normalized']);
            $doctor->save();

            $doctorProfile = $doctor->profile()->firstOrCreate();
            $doctorProfile->fill($profile);
            $changes = [...$changes, ...array_map(fn ($k) => "profile.{$k}", array_keys($doctorProfile->getDirty()))];
            $profileValues = self::values($doctorProfile, except: ['updated_at'], prefix: 'profile.');
            $doctorProfile->save();

            if ($changes !== []) {
                activity()
                    ->performedOn($doctor)
                    ->causedBy($actor)
                    ->event('doctor.updated')
                    ->withProperties(['fields' => $changes, 'verification_revoked' => $licenseChanged && in_array('verification_status', $changes, true)])
                    ->withChanges([
                        'old' => [...$values['old'], ...$profileValues['old']],
                        'attributes' => [...$values['new'], ...$profileValues['new']],
                    ])
                    ->log('doctor.updated');
            }

            return $doctor;
        }));
    }

    /**
     * Valores en bruto (como se guardan) de los campos modificados, antes y después.
     *
     * @param  array<int, string>  $except
     * @return array{old: array<string, mixed>, new: array<string, mixed>}
     */
    private static function values(Model $model, array $except, string $prefix = ''): array
    {
        $values = ['old' => [], 'new' => []];

        foreach (Arr::except($model->getDirty(), $except) as $key => $value) {
            $values['old'][$prefix.$key] = $model->getRawOriginal($key);
            $values['new'][$prefix.$key] = $value;
        }

        return $values;
    }
}
