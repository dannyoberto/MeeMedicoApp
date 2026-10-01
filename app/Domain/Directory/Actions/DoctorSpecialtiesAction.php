<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Enums\SpecialtyStatus;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Models\User;

/**
 * Especialidades de un médico. Como máximo una principal (doctor_specialties_one_primary_uniq):
 * la primera que se añade lo es, y quitar la principal promueve otra.
 */
class DoctorSpecialtiesAction
{
    public function attach(Doctor $doctor, Specialty $specialty, ?User $actor, bool $primary = false): void
    {
        if ($specialty->status !== SpecialtyStatus::Active) {
            throw new DirectoryRuleException("La especialidad {$specialty->name} está inactiva.");
        }

        if ($doctor->specialties()->whereKey($specialty->getKey())->exists()) {
            throw new DirectoryRuleException("{$specialty->name} ya está asignada a esta ficha.");
        }

        DoctorAggregateChange::apply($doctor, $actor, ['part' => 'specialties', 'op' => 'attached', 'specialty' => $specialty->slug], function () use ($doctor, $specialty, $primary) {
            $makePrimary = $primary || ! $doctor->specialties()->wherePivot('is_primary', true)->exists();

            if ($makePrimary) {
                $this->clearPrimary($doctor);
            }

            $doctor->specialties()->attach($specialty->getKey(), ['is_primary' => $makePrimary]);
        });
    }

    public function detach(Doctor $doctor, Specialty $specialty, ?User $actor): void
    {
        DoctorAggregateChange::apply($doctor, $actor, ['part' => 'specialties', 'op' => 'detached', 'specialty' => $specialty->slug], function () use ($doctor, $specialty) {
            $wasPrimary = (bool) $doctor->specialties()->whereKey($specialty->getKey())->first()?->pivot->is_primary;

            $doctor->specialties()->detach($specialty->getKey());

            if ($wasPrimary) {
                $next = $doctor->specialties()->orderByPivot('created_at')->first();
                $next && $doctor->specialties()->updateExistingPivot($next->getKey(), ['is_primary' => true]);
            }
        });
    }

    public function makePrimary(Doctor $doctor, Specialty $specialty, ?User $actor): void
    {
        DoctorAggregateChange::apply($doctor, $actor, ['part' => 'specialties', 'op' => 'primary', 'specialty' => $specialty->slug], function () use ($doctor, $specialty) {
            // Primero se desmarca la anterior: el índice parcial no admite dos principales ni un instante.
            $this->clearPrimary($doctor);
            $doctor->specialties()->updateExistingPivot($specialty->getKey(), ['is_primary' => true]);
        });
    }

    private function clearPrimary(Doctor $doctor): void
    {
        $doctor->specialties()->newPivotStatement()
            ->where('doctor_id', $doctor->getKey())
            ->update(['is_primary' => false]);
    }
}
