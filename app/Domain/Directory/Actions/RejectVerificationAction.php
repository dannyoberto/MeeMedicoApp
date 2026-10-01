<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Cdn\DoctorCachePurge;
use App\Domain\Directory\Enums\VerificationStatus;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * La licencia no se pudo contrastar. Si la ficha estaba verificada, pierde la insignia;
 * la traza anterior queda en la auditoría.
 */
class RejectVerificationAction
{
    public function execute(Doctor $doctor, User $reviewer, string $reason): void
    {
        DoctorCachePurge::around($doctor, fn () => DB::transaction(function () use ($doctor, $reviewer, $reason) {
            $doctor->forceFill([
                'verification_status' => VerificationStatus::Rejected,
                'verification_source' => null,
                'verified_at' => null,
                'verified_by_user_id' => null,
                'license_verified_at' => null,
            ])->save();

            activity()
                ->performedOn($doctor)
                ->causedBy($reviewer)
                ->event('doctor.verification_rejected')
                ->withProperties(['reason' => $reason])
                ->log('doctor.verification_rejected');
        }));
    }
}
