<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Cdn\DoctorCachePurge;
use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use App\Domain\Directory\Support\PublicationRequirements;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Publica una ficha si pasa la puerta de calidad (AGENTS.md §3, DATABASE.md §11).
 * La usan el backoffice y, en la Etapa 6, import:publish fila por fila.
 */
class PublishDoctorAction
{
    /**
     * @throws DirectoryRuleException con los requisitos pendientes en `details`
     */
    public function execute(Doctor $doctor, ?User $actor): void
    {
        match ($doctor->status) {
            DoctorStatus::Active => null,
            DoctorStatus::Suspended => throw new DirectoryRuleException('La ficha está suspendida: levanta la suspensión antes de publicarla.'),
            DoctorStatus::Merged => throw new DirectoryRuleException('La ficha fue fusionada con otra: no se puede publicar.'),
            default => $this->publish($doctor, $actor),
        };
    }

    private function publish(Doctor $doctor, ?User $actor): void
    {
        if ($missing = PublicationRequirements::missing($doctor)) {
            throw new DirectoryRuleException('La ficha no cumple los requisitos para publicarse.', array_values($missing));
        }

        DoctorCachePurge::around($doctor, fn () => DB::transaction(function () use ($doctor, $actor) {
            $doctor->forceFill(['status' => DoctorStatus::Active, 'published_at' => now()])->save();

            activity()
                ->performedOn($doctor)
                ->causedBy($actor)
                ->event('doctor.published')
                ->log('doctor.published');
        }));
    }
}
