<?php

use App\Domain\Directory\Actions\CreateDoctorAction;
use App\Domain\Directory\Actions\CreateSuppressionAction;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

it('crea la ficha en borrador junto con su perfil, en la misma transacción', function () {
    $doctor = makeDoctor(['first_name' => 'Dr. Juan', 'last_name' => 'Pérez']);

    expect($doctor->status->value)->toBe('draft')
        ->and($doctor->profile()->exists())->toBeTrue()
        ->and($doctor->name_normalized)->toBe('juan perez')
        ->and($doctor->slug)->toBe('juan-perez')
        ->and(Activity::where('subject_id', $doctor->id)->where('event', 'doctor.created')->exists())->toBeTrue();
});

it('asigna origen "admin" a una licencia sin origen: toda licencia necesita procedencia', function () {
    expect(makeDoctor(['license_number' => 'MED-1'])->license_source->value)->toBe('admin');
});

it('rechaza una licencia repetida en el mismo país', function () {
    makeDoctor(['license_number' => 'MED-1']);

    expect(fn () => makeDoctor(['first_name' => 'Otro', 'license_number' => 'MED-1']))
        ->toThrow(ValidationException::class);
});

it('permite la misma licencia en otro país', function () {
    makeDoctor(['license_number' => 'MED-1']);

    expect(makeDoctor(['country_id' => country('GT')->id, 'license_number' => 'MED-1'])->exists)->toBeTrue();
});

describe('supresiones (AGENTS.md §4: se consulta antes de crear cualquier ficha)', function () {
    it('bloquea siempre si coincide por licencia', function () {
        app(CreateSuppressionAction::class)->execute(country(), 'LIC-X', null, null, null, 'pidió salir', now(), null);

        expect(fn () => makeDoctor(['license_number' => 'LIC-X']))->toThrow(DirectoryRuleException::class);
    });

    it('si solo coincide el nombre, exige confirmar que es un homónimo', function () {
        app(CreateSuppressionAction::class)->execute(country(), null, null, null, 'Ana Rojas', 'pidió salir', now(), null);

        expect(fn () => makeDoctor())->toThrow(ValidationException::class);

        $doctor = app(CreateDoctorAction::class)->execute(
            ['country_id' => country()->id, 'first_name' => 'Ana', 'last_name' => 'Rojas'], null, confirmHomonym: true,
        );
        expect($doctor->exists)->toBeTrue();
    });

    it('no afecta a otro país', function () {
        app(CreateSuppressionAction::class)->execute(country('GT'), 'LIC-X', null, null, null, 'x', now(), null);

        expect(makeDoctor(['license_number' => 'LIC-X'])->exists)->toBeTrue();
    });
});
