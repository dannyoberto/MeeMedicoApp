<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Enums\DoctorSource;
use App\Domain\Directory\Enums\LicenseSource;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use App\Domain\Directory\Support\DoctorSlugGenerator;
use App\Domain\Directory\Support\NameNormalizer;
use App\Domain\Directory\Support\SuppressionCheck;
use App\Models\Country;
use App\Models\Doctor;
use App\Models\ImportBatch;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alta de una ficha (DATABASE.md §11). Nace en borrador: publicar es otro paso.
 * El DoctorProfile se crea SIEMPRE en la misma transacción (§9.2): es la condición
 * de que la tabla exista separada.
 */
class CreateDoctorAction
{
    /**
     * @param  array{country_id: string, first_name: string, last_name: string, professional_name?: ?string, gender?: ?string, license_number?: ?string, license_source?: ?string}  $data
     * @param  bool  $confirmHomonym  el operador confirmó que no es la persona de una supresión que solo coincide por nombre
     *
     * @throws DirectoryRuleException si coincide con una supresión por licencia
     * @throws ValidationException (claves: license_number, confirm_homonym)
     */
    public function execute(
        array $data,
        ?User $actor,
        bool $confirmHomonym = false,
        DoctorSource $source = DoctorSource::Admin,
        ?ImportBatch $importBatch = null,
        ?Specialty $primarySpecialty = null,
    ): Doctor {
        $country = Country::findOrFail($data['country_id']);
        $firstName = trim($data['first_name']);
        $lastName = trim($data['last_name']);
        $license = filled($data['license_number'] ?? null) ? trim($data['license_number']) : null;
        $nameNormalized = NameNormalizer::normalize("{$firstName} {$lastName}");

        if ($suppression = SuppressionCheck::strongMatch($country->getKey(), $license)) {
            throw new DirectoryRuleException(
                'Esta persona pidió no aparecer en el directorio (supresión registrada el '
                .$suppression->requested_at->format('d/m/Y').'). No se puede crear la ficha.',
            );
        }

        if (! $confirmHomonym && SuppressionCheck::nameMatch($country->getKey(), $nameNormalized)) {
            throw ValidationException::withMessages([
                'confirm_homonym' => 'Hay una supresión con este mismo nombre en '.$country->name.'. Si es otra persona (homónimo), confírmalo para continuar.',
            ]);
        }

        if ($license && Doctor::where('country_id', $country->getKey())->where('license_number', $license)->exists()) {
            throw ValidationException::withMessages(['license_number' => 'Ya existe una ficha con ese número de colegiado en '.$country->name.'.']);
        }

        return DB::transaction(function () use ($data, $country, $firstName, $lastName, $license, $nameNormalized, $actor, $source, $importBatch, $primarySpecialty) {
            $doctor = new Doctor([
                'country_id' => $country->getKey(),
                'created_by_user_id' => $actor?->getKey(),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'professional_name' => filled($data['professional_name'] ?? null) ? trim($data['professional_name']) : null,
                'gender' => $data['gender'] ?? null,
                'license_number' => $license,
                // Toda licencia necesita origen: sin él no se puede juzgar cuánto fiarse.
                'license_source' => $license ? ($data['license_source'] ?? LicenseSource::Admin->value) : null,
                'source' => $source,
                'import_batch_id' => $importBatch?->getKey(),
            ]);

            $doctor->forceFill([
                'name_normalized' => $nameNormalized,
                // Con especialidad, un homónimo obtiene juan-perez-cardiologia y no juan-perez-2.
                'slug' => DoctorSlugGenerator::generate($country->getKey(), $firstName, $lastName, $primarySpecialty),
            ])->save();

            $doctor->profile()->create();

            activity()
                ->performedOn($doctor)
                ->causedBy($actor)
                ->event('doctor.created')
                ->withProperties(['slug' => $doctor->slug, 'source' => $source->value])
                ->log('doctor.created');

            return $doctor;
        });
    }
}
