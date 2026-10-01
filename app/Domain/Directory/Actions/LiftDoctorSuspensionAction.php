<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Enums\DoctorStatus;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Levanta la suspensión dejando la ficha despublicada: volver al sitio público
 * pasa otra vez por la puerta de calidad de PublishDoctorAction.
 */
class LiftDoctorSuspensionAction
{
    public function execute(Doctor $doctor, ?User $actor): void
    {
        if ($doctor->status !== DoctorStatus::Suspended) {
            return;
        }

        DB::transaction(function () use ($doctor, $actor) {
            $doctor->forceFill(['status' => DoctorStatus::Inactive])->save();

            activity()
                ->performedOn($doctor)
                ->causedBy($actor)
                ->event('doctor.suspension_lifted')
                ->log('doctor.suspension_lifted');
        });
    }
}
