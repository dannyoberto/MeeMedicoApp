<?php

use App\Domain\Directory\Actions\CreateSuppressionAction;
use App\Domain\Directory\Actions\SetCatalogStatusAction;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use App\Domain\Directory\Support\SuppressionMatches;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

describe('catálogos: no se desactiva lo que usa una ficha publicada', function () {
    it('rechaza desactivar ciudad, especialidad o país en uso', function (Closure $entity) {
        publishedDoctor();
        country()->forceFill(['status' => 'active'])->save(); // los países se siembran inactivos

        expect(fn () => app(SetCatalogStatusAction::class)->execute($entity(), false, null))->toThrow(DirectoryRuleException::class);
    })->with([
        'ciudad' => [fn () => city()],
        'especialidad' => [fn () => specialty()],
        'país' => [fn () => country()],
    ]);

    it('permite desactivar lo que no usa ninguna ficha publicada, y lo audita', function () {
        publishedDoctor();
        app(SetCatalogStatusAction::class)->execute(specialty('alergologia'), false, null);

        expect(specialty('alergologia')->status->value)->toBe('inactive')
            ->and(Activity::where('subject_id', specialty('alergologia')->id)->where('event', 'catalog.deactivated')->exists())->toBeTrue();
    });

    it('un borrador no bloquea la desactivación', function () {
        publishableDoctor();
        app(SetCatalogStatusAction::class)->execute(city(), false, null);

        expect(city()->status->value)->toBe('inactive');
    });
});

describe('supresiones (DATABASE.md §14.1)', function () {
    it('normaliza las claves y audita', function () {
        $suppression = app(CreateSuppressionAction::class)->execute(
            country('DO'), null, ' Juan@Clinica.com ', '(829) 555-1234', 'Dr. Juan Pérez', 'Correo', now(), null,
        );

        expect($suppression)
            ->phone_normalized->toBe('+18295551234')
            ->email_normalized->toBe('juan@clinica.com')
            ->name_normalized->toBe('juan perez')
            ->and(Activity::where('subject_id', $suppression->id)->where('event', 'suppression.created')->exists())->toBeTrue();
    });

    it('exige al menos una clave válida', function (array $keys) {
        expect(fn () => app(CreateSuppressionAction::class)->execute(country(), ...[...$keys, 'motivo', now(), null]))
            ->toThrow(ValidationException::class);
    })->with([
        'ninguna' => [[null, null, null, null]],
        'teléfono inválido' => [[null, null, '123', null]],
        'correo inválido' => [[null, 'x', null, null]],
    ]);

    it('encuentra las fichas existentes que coinciden, sin despublicarlas', function () {
        $doctor = publishedDoctor(['license_number' => 'MED-7']);
        $suppression = app(CreateSuppressionAction::class)->execute(country(), 'MED-7', null, null, null, 'x', now(), null);

        expect(SuppressionMatches::for($suppression)->modelKeys())->toBe([$doctor->id])
            ->and($doctor->fresh()->status->value)->toBe('active');
    });

    it('también coincide por teléfono de contacto', function () {
        $doctor = publishedDoctor();
        $suppression = app(CreateSuppressionAction::class)->execute(country(), null, null, '2222-3333', null, 'x', now(), null);

        expect(SuppressionMatches::for($suppression)->modelKeys())->toBe([$doctor->id]);
    });
});
