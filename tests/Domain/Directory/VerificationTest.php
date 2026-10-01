<?php

use App\Domain\Directory\Actions\RejectVerificationAction;
use App\Domain\Directory\Actions\UpdateDoctorAction;
use App\Domain\Directory\Actions\VerifyDoctorAction;
use App\Domain\Directory\Enums\VerificationSource;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use Spatie\Activitylog\Models\Activity;

/*
| MODELO-IDENTIDAD.md §8: verificar es contrastar contra el registro oficial y hay que
| poder responder quién, cuándo y con qué. La insignia no es transferible.
*/

it('no verifica una ficha sin número de colegiado', function () {
    expect(fn () => app(VerifyDoctorAction::class)->execute(makeDoctor(), VerificationSource::Manual, admin()))
        ->toThrow(DirectoryRuleException::class);
});

it('verificar deja la traza completa', function () {
    $admin = admin();
    $doctor = makeDoctor(['license_number' => 'MED-1']);
    app(VerifyDoctorAction::class)->execute($doctor, VerificationSource::OfficialRegistry, $admin);

    expect($doctor->fresh())
        ->verification_status->value->toBe('verified')
        ->verification_source->value->toBe('official_registry')
        ->verified_by_user_id->toBe($admin->id)
        ->verified_at->not->toBeNull()
        ->and(Activity::where('subject_id', $doctor->id)->where('event', 'doctor.verified')->exists())->toBeTrue();
});

it('corregir el nombre conserva la verificación y no cambia el slug', function () {
    $doctor = makeDoctor(['license_number' => 'MED-1']);
    app(VerifyDoctorAction::class)->execute($doctor, VerificationSource::Manual, admin());
    app(UpdateDoctorAction::class)->execute($doctor, ['first_name' => 'Ana María'], null);

    expect($doctor->fresh())
        ->verification_status->value->toBe('verified')
        ->name_normalized->toBe('ana maria rojas')
        ->slug->toBe('ana-rojas');
});

it('cambiar el número de colegiado retira la verificación', function () {
    $doctor = makeDoctor(['license_number' => 'MED-1']);
    app(VerifyDoctorAction::class)->execute($doctor, VerificationSource::Manual, admin());
    app(UpdateDoctorAction::class)->execute($doctor, ['license_number' => 'MED-2'], null);

    expect($doctor->fresh())
        ->verification_status->value->toBe('unverified')
        ->verified_at->toBeNull()
        ->verified_by_user_id->toBeNull();
});

it('rechazar guarda el motivo y quita la insignia', function () {
    $doctor = makeDoctor(['license_number' => 'MED-1']);
    app(VerifyDoctorAction::class)->execute($doctor, VerificationSource::Manual, admin());
    app(RejectVerificationAction::class)->execute($doctor, admin('otro@t.l'), 'No figura en el colegio');

    expect($doctor->fresh()->verification_status->value)->toBe('rejected')
        ->and(Activity::where('subject_id', $doctor->id)->where('event', 'doctor.verification_rejected')->first()->properties['reason'])
        ->toBe('No figura en el colegio');
});
