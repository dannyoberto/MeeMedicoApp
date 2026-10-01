<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Cdn\DoctorCachePurge;
use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Suspensión: retirada por una causa (queja, suplantación, orden de retirada), distinta
 * de despublicar. Queda en la auditoría con su motivo.
 */
class SuspendDoctorAction
{
    public function execute(Doctor $doctor, ?User $actor, string $reason): void
    {
        if ($doctor->status === DoctorStatus::Suspended) {
            return;
        }

        if ($doctor->status === DoctorStatus::Merged) {
            throw new DirectoryRuleException('La ficha fue fusionada con otra: suspende la ficha superviviente.');
        }

        DoctorCachePurge::around($doctor, fn () => DB::transaction(function () use ($doctor, $actor, $reason) {
            $doctor->forceFill(['status' => DoctorStatus::Suspended])->save();

            activity()
                ->performedOn($doctor)
                ->causedBy($actor)
                ->event('doctor.suspended')
                ->withProperties(['reason' => $reason])
                ->log('doctor.suspended');
        }));
    }
}
