<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Cdn\DoctorCachePurge;
use App\Domain\Directory\Enums\DoctorStatus;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Retira una ficha del sitio público. published_at se conserva como histórico (§11).
 */
class UnpublishDoctorAction
{
    public function execute(Doctor $doctor, ?User $actor, ?string $reason = null): void
    {
        if ($doctor->status !== DoctorStatus::Active) {
            return;
        }

        DoctorCachePurge::around($doctor, fn () => DB::transaction(function () use ($doctor, $actor, $reason) {
            $doctor->forceFill(['status' => DoctorStatus::Inactive])->save();

            activity()
                ->performedOn($doctor)
                ->causedBy($actor)
                ->event('doctor.unpublished')
                ->withProperties(array_filter(['reason' => $reason]))
                ->log('doctor.unpublished');
        }));
    }
}
