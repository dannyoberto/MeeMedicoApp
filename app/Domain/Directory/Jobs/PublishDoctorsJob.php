<?php

namespace App\Domain\Directory\Jobs;

use App\Domain\Directory\Actions\PublishDoctorsAction;
use App\Domain\Directory\Events\DoctorsPublished;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Publicación en lote de una selección grande del backoffice. Cada ficha pasa por
 * PublishDoctorAction; al terminar se avisa a quien la lanzó (DoctorsPublished).
 */
class PublishDoctorsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    /**
     * @param  array<int, string>  $doctorIds
     */
    public function __construct(
        public readonly array $doctorIds,
        public readonly ?string $actorId = null,
    ) {}

    public function handle(PublishDoctorsAction $publish): void
    {
        $actor = $this->actorId ? User::find($this->actorId) : null;

        $report = $publish->execute(Doctor::whereKey($this->doctorIds)->lazyById(200), $actor);

        DoctorsPublished::dispatch($report, $this->actorId);
    }
}
