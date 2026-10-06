<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Enums\InsurerStatus;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use App\Models\Facility;
use App\Models\Insurer;
use App\Models\User;

/**
 * Convenios del establecimiento con aseguradoras (DATABASE.md §9.14). Independientes de
 * las aseguradoras de sus médicos: que el hospital tenga convenio con BMI no significa
 * que todos sus médicos atiendan por BMI.
 */
class FacilityInsurersAction
{
    /**
     * @throws DirectoryRuleException
     */
    public function attach(Facility $facility, Insurer $insurer, ?User $actor): void
    {
        if ($insurer->status !== InsurerStatus::Active) {
            throw new DirectoryRuleException("La aseguradora {$insurer->name} está inactiva.");
        }

        if ($insurer->country_id !== $facility->country_id) {
            throw new DirectoryRuleException('La aseguradora es de otro país que el establecimiento.');
        }

        if ($facility->insurers()->whereKey($insurer->getKey())->exists()) {
            return;
        }

        FacilityAggregateChange::apply($facility, $actor, ['part' => 'insurers', 'op' => 'attached', 'insurer' => $insurer->name],
            fn () => $facility->insurers()->attach($insurer->getKey()));
    }

    public function detach(Facility $facility, Insurer $insurer, ?User $actor): void
    {
        if (! $facility->insurers()->whereKey($insurer->getKey())->exists()) {
            return;
        }

        FacilityAggregateChange::apply($facility, $actor, ['part' => 'insurers', 'op' => 'detached', 'insurer' => $insurer->name],
            fn () => $facility->insurers()->detach($insurer->getKey()));
    }
}
