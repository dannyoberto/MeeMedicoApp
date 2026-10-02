<?php

use App\Domain\Directory\Actions\PublishDoctorsAction;
use App\Domain\Directory\Actions\UpdateDoctorAction;
use App\Domain\Directory\Jobs\PublishDoctorsJob;
use Spatie\Activitylog\Models\Activity;

/*
| Publicar en lote pasa cada ficha por la misma puerta de calidad que publicar una
| (PublishDoctorAction): una que no pasa no detiene a las demás.
*/

it('publica las que cumplen e informa de las demás con su motivo', function () {
    $ready = publishableDoctor(['first_name' => 'Lista']);
    $incomplete = makeDoctor(['first_name' => 'Incompleta']);
    $already = publishedDoctor(['first_name' => 'Publicada', 'license_number' => 'MED-9']);

    $report = app(PublishDoctorsAction::class)->execute(collect([$ready, $incomplete, $already]), admin());

    expect($report['published'])->toBe(1)
        ->and($report['already'])->toBe(1)
        ->and($report['skipped'])->toBe(1)
        ->and(array_keys($report['reasons']))->toContain('Un contacto público')
        ->and($ready->fresh()->status->value)->toBe('active')
        ->and($incomplete->fresh()->status->value)->toBe('draft')
        ->and(PublishDoctorsAction::reasonLines($report))->toContain('1 sin: Un contacto público');
});

it('en segundo plano, avisa al terminar a quien la lanzó', function () {
    $admin = admin();
    $doctor = publishableDoctor();

    PublishDoctorsJob::dispatch([$doctor->id], $admin->id);

    expect($doctor->fresh()->status->value)->toBe('active')
        ->and($admin->notifications()->firstOrFail()->data['title'])->toBe('Publicación en lote terminada');
});

it('la auditoría de una edición guarda el valor anterior y el nuevo', function () {
    $doctor = makeDoctor();

    app(UpdateDoctorAction::class)->execute($doctor, ['last_name' => 'Rojas Mora', 'bio' => 'Cardióloga'], null);

    $changes = Activity::where('subject_id', $doctor->id)->where('event', 'doctor.updated')->latest('id')->firstOrFail()->attribute_changes;
    expect($changes['old'])->toBe(['last_name' => 'Rojas', 'profile.bio' => null])
        ->and($changes['attributes'])->toBe(['last_name' => 'Rojas Mora', 'profile.bio' => 'Cardióloga']);
});
