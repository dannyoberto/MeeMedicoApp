<?php

use App\Domain\Directory\Actions\DoctorContactsAction;
use App\Domain\Directory\Actions\DoctorLocationsAction;
use App\Domain\Directory\Actions\DoctorSpecialtiesAction;
use App\Domain\Directory\Actions\LiftDoctorSuspensionAction;
use App\Domain\Directory\Actions\PublishDoctorAction;
use App\Domain\Directory\Actions\SuspendDoctorAction;
use App\Domain\Directory\Actions\UnpublishDoctorAction;
use App\Domain\Directory\Actions\UpdateDoctorAction;
use App\Domain\Directory\Cdn\PurgeCdnPaths;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use Illuminate\Support\Facades\Bus;
use Spatie\Activitylog\Models\Activity;

/*
| AGENTS.md §3: no se publica sin especialidad, ubicación con ciudad y contacto público,
| y una ficha publicada nunca queda incompleta (decisión de la Etapa 3: se bloquea).
*/

describe('puerta de calidad', function () {
    it('no publica una ficha incompleta y lista lo que falta', function () {
        $doctor = makeDoctor();

        try {
            app(PublishDoctorAction::class)->execute($doctor, null);
            $this->fail('Debió rechazarse');
        } catch (DirectoryRuleException $e) {
            expect($e->details)->toHaveCount(3);
        }

        expect($doctor->fresh()->status->value)->toBe('draft');
    });

    it('publica una ficha completa, fija published_at y lo audita', function () {
        $doctor = publishedDoctor();

        expect($doctor->status->value)->toBe('active')
            ->and($doctor->published_at)->not->toBeNull()
            ->and(Activity::where('subject_id', $doctor->id)->where('event', 'doctor.published')->exists())->toBeTrue();
    });

    it('exige que la especialidad principal esté activa', function () {
        $doctor = publishableDoctor();
        specialty()->forceFill(['status' => 'inactive'])->save();

        expect(fn () => app(PublishDoctorAction::class)->execute($doctor, null))->toThrow(DirectoryRuleException::class);
    });

    it('purga la ficha y cada listado especialidad × ciudad de la ubicación principal', function () {
        $doctor = publishableDoctor();
        app(DoctorSpecialtiesAction::class)->attach($doctor, specialty('pediatria'), null);
        Bus::fake([PurgeCdnPaths::class]);

        app(PublishDoctorAction::class)->execute($doctor, null);

        Bus::assertDispatched(PurgeCdnPaths::class, fn ($job) => collect([
            '/costa-rica/medicos/ana-rojas', '/costa-rica/cardiologia/escazu', '/costa-rica/pediatria/escazu',
        ])->every(fn ($p) => in_array($p, $job->paths)));
    });

    it('no purga nada al editar un borrador: no tiene páginas cacheadas', function () {
        $doctor = makeDoctor();
        Bus::fake([PurgeCdnPaths::class]);

        app(UpdateDoctorAction::class)->execute($doctor, ['first_name' => 'Ana María'], null);

        Bus::assertNotDispatched(PurgeCdnPaths::class);
    });
});

describe('una ficha publicada nunca queda incompleta', function () {
    it('bloquea quitar la última especialidad, ubicación o contacto público', function (Closure $remove) {
        $doctor = publishedDoctor();

        expect(fn () => $remove($doctor))->toThrow(DirectoryRuleException::class);
    })->with([
        'especialidad' => [fn ($d) => app(DoctorSpecialtiesAction::class)->detach($d, specialty(), null)],
        'ubicación' => [fn ($d) => app(DoctorLocationsAction::class)->detach($d, $d->locations()->first(), null)],
        'contacto' => [fn ($d) => app(DoctorContactsAction::class)->remove($d, $d->contacts()->first(), null)],
        'ocultar el contacto' => [fn ($d) => app(DoctorContactsAction::class)->save($d, $d->contacts()->first(), ['type' => 'phone', 'value' => '2222-3333', 'is_public' => false], null)],
    ]);

    it('tras un bloqueo, la ficha y el agregado quedan intactos', function () {
        $doctor = publishedDoctor();
        $contact = $doctor->contacts()->first();

        rescue(fn () => app(DoctorContactsAction::class)->remove($doctor, $contact, null), report: false);

        expect($doctor->contacts()->count())->toBe(1)
            ->and($contact->exists)->toBeTrue()
            ->and($doctor->fresh()->status->value)->toBe('active');
    });

    it('en borrador sí se puede quitar lo último', function () {
        $doctor = publishableDoctor();
        app(DoctorSpecialtiesAction::class)->detach($doctor, specialty(), null);

        expect($doctor->specialties()->count())->toBe(0);
    });
});

describe('despublicar y suspender', function () {
    it('despublicar conserva published_at como histórico', function () {
        $doctor = publishedDoctor();
        app(UnpublishDoctorAction::class)->execute($doctor, null);

        expect($doctor->fresh()->status->value)->toBe('inactive')
            ->and($doctor->fresh()->published_at)->not->toBeNull();
    });

    it('suspender con motivo; una suspendida no se publica; levantar la deja despublicada', function () {
        $doctor = publishedDoctor();
        app(SuspendDoctorAction::class)->execute($doctor, null, 'Suplantación');

        expect($doctor->fresh()->status->value)->toBe('suspended')
            ->and(Activity::where('subject_id', $doctor->id)->where('event', 'doctor.suspended')->first()->properties['reason'])->toBe('Suplantación')
            ->and(fn () => app(PublishDoctorAction::class)->execute($doctor->fresh(), null))->toThrow(DirectoryRuleException::class);

        app(LiftDoctorSuspensionAction::class)->execute($doctor->fresh(), null);
        expect($doctor->fresh()->status->value)->toBe('inactive');
    });
});
