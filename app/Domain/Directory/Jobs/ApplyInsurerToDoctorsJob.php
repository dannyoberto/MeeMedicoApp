<?php

namespace App\Domain\Directory\Jobs;

use App\Domain\Directory\Actions\DoctorInsurersAction;
use App\Domain\Directory\Events\InsurerAppliedToDoctors;
use App\Models\Doctor;
use App\Models\Insurer;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Asignar o quitar una aseguradora a una selección grande de médicos. Cada uno pasa
 * por DoctorInsurersAction; al terminar se avisa a quien lo lanzó.
 */
class ApplyInsurerToDoctorsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    /**
     * @param  array<int, string>  $doctorIds
     */
    public function __construct(
        public readonly string $insurerId,
        public readonly array $doctorIds,
        public readonly bool $attach,
        public readonly ?string $actorId = null,
    ) {}

    public function handle(DoctorInsurersAction $insurers): void
    {
        $actor = $this->actorId ? User::find($this->actorId) : null;
        $insurer = Insurer::findOrFail($this->insurerId);

        $report = $insurers->applyToMany($insurer, Doctor::whereKey($this->doctorIds)->lazyById(200), $this->attach, $actor);

        InsurerAppliedToDoctors::dispatch($insurer->name, $this->attach, $report, $this->actorId);
    }
}
