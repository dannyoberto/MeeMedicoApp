<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Cdn\DoctorCachePurge;
use App\Domain\Directory\Enums\VerificationSource;
use App\Domain\Directory\Enums\VerificationStatus;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Verificar es contrastar contra el registro oficial (MODELO-IDENTIDAD.md §8), y hay
 * que poder responder quién, cuándo y con qué. El CHECK
 * doctors_verified_requires_trace_chk respalda la traza en la base.
 */
class VerifyDoctorAction
{
    public function execute(Doctor $doctor, VerificationSource $source, User $verifier, ?string $notes = null): void
    {
        if (blank($doctor->license_number)) {
            throw new DirectoryRuleException('No se puede verificar una ficha sin número de colegiado: es lo que se contrasta.');
        }

        // La insignia cambia la ficha pública: se purga si está publicada.
        DoctorCachePurge::around($doctor, fn () => DB::transaction(function () use ($doctor, $source, $verifier, $notes) {
            $doctor->forceFill([
                'verification_status' => VerificationStatus::Verified,
                'verification_source' => $source,
                'verified_at' => now(),
                'verified_by_user_id' => $verifier->getKey(),
                'license_verified_at' => now(),
            ])->save();

            activity()
                ->performedOn($doctor)
                ->causedBy($verifier)
                ->event('doctor.verified')
                ->withProperties(array_filter(['source' => $source->value, 'license' => $doctor->license_number, 'notes' => $notes]))
                ->log('doctor.verified');
        }));
    }
}
