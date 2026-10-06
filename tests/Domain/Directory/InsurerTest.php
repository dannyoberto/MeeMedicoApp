<?php

use App\Domain\Directory\Actions\DoctorInsurersAction;
use App\Domain\Directory\Actions\FacilityInsurersAction;
use App\Domain\Directory\Actions\SetCatalogStatusAction;
use App\Domain\Directory\Actions\UpdateSlugAction;
use App\Domain\Directory\Cdn\PurgeCdnPaths;
use App\Domain\Directory\Events\InsurerAppliedToDoctors;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use App\Domain\Directory\Jobs\ApplyInsurerToDoctorsJob;
use App\Models\SlugRedirect;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Models\Activity;

/*
| Seguros (DATABASE.md §9.13–§9.14, MODULO-ESTABLECIMIENTOS-SEGUROS.md §5.5–§5.7): solo
| aseguradoras activas del mismo país, por médico y por establecimiento, sin planes.
*/

describe('aseguradoras del médico', function () {
    it('asigna una aseguradora de su país, lo audita y es idempotente', function () {
        $doctor = makeDoctor();
        $bmi = insurer();

        app(DoctorInsurersAction::class)->attach($doctor, $bmi, null);
        app(DoctorInsurersAction::class)->attach($doctor, $bmi, null);

        $audit = Activity::where('subject_id', $doctor->id)->where('event', 'doctor.updated')->get();
        expect($doctor->insurers()->pluck('name')->all())->toBe(['BMI Seguros'])
            ->and($audit)->toHaveCount(1)
            ->and($audit->first()->properties->all())->toMatchArray(['part' => 'insurers', 'op' => 'attached', 'insurer' => 'BMI Seguros']);
    });

    it('rechaza una aseguradora de otro país o inactiva', function () {
        $doctor = makeDoctor();

        expect(fn () => app(DoctorInsurersAction::class)->attach($doctor, insurer('IGSS', 'GT'), null))->toThrow(DirectoryRuleException::class, 'otro país')
            ->and(fn () => app(DoctorInsurersAction::class)->attach($doctor, insurer('Vieja', 'CR', ['status' => 'inactive']), null))->toThrow(DirectoryRuleException::class, 'inactiva');
    });

    it('quitarla de una ficha publicada purga su página', function () {
        $doctor = publishedDoctor();
        app(DoctorInsurersAction::class)->attach($doctor, $bmi = insurer(), null);
        Bus::fake([PurgeCdnPaths::class]);

        app(DoctorInsurersAction::class)->detach($doctor, $bmi, null);

        expect($doctor->insurers()->count())->toBe(0);
        Bus::assertDispatched(PurgeCdnPaths::class, fn ($job) => in_array('/costa-rica/medicos/ana-rojas', $job->paths));
    });

    it('asigna en lote: cuenta las nuevas, las que ya la tenían y las de otro país', function () {
        $bmi = insurer();
        $a = makeDoctor(['first_name' => 'Ana']);
        $b = makeDoctor(['first_name' => 'Luis']);
        $gt = makeDoctor(['first_name' => 'Eva', 'country_id' => country('GT')->id]);
        app(DoctorInsurersAction::class)->attach($b, $bmi, null);

        $report = app(DoctorInsurersAction::class)->applyToMany($bmi, [$a, $b, $gt], true, null);

        expect($report)->toMatchArray(['changed' => 1, 'unchanged' => 1, 'skipped' => 1])
            ->and(DoctorInsurersAction::reportLines($report, true))->toBe(['1 asignadas, 1 ya la tenían, 1 omitidas.', '1: La aseguradora es de otro país que el médico.']);
    });

    it('el lote en segundo plano avisa al terminar', function () {
        Event::fake([InsurerAppliedToDoctors::class]);
        $bmi = insurer();
        $doctor = makeDoctor();

        (new ApplyInsurerToDoctorsJob($bmi->id, [$doctor->id], true))->handle(app(DoctorInsurersAction::class));

        expect($doctor->insurers()->count())->toBe(1);
        Event::assertDispatched(InsurerAppliedToDoctors::class, fn ($e) => $e->report['changed'] === 1 && $e->insurerName === 'BMI Seguros');
    });
});

describe('convenios del establecimiento', function () {
    it('son independientes de las aseguradoras de sus médicos', function () {
        $facility = activeFacility();
        app(FacilityInsurersAction::class)->attach($facility, $bmi = insurer(), null);

        expect($facility->insurers()->pluck('name')->all())->toBe(['BMI Seguros'])
            ->and($bmi->doctors()->count())->toBe(0)
            ->and(Activity::where('subject_id', $facility->id)->where('event', 'facility.updated')->latest('id')->value('properties')['part'])->toBe('insurers');
    });

    it('rechaza una aseguradora de otro país o inactiva', function () {
        $facility = makeFacility();

        expect(fn () => app(FacilityInsurersAction::class)->attach($facility, insurer('IGSS', 'GT'), null))->toThrow(DirectoryRuleException::class)
            ->and(fn () => app(FacilityInsurersAction::class)->attach($facility, insurer('Vieja', 'CR', ['status' => 'inactive']), null))->toThrow(DirectoryRuleException::class);
    });
});

describe('catálogo', function () {
    it('desactivar una aseguradora conserva sus vínculos aunque la usen fichas publicadas', function () {
        $doctor = publishedDoctor();
        app(DoctorInsurersAction::class)->attach($doctor, $bmi = insurer(), null);

        app(SetCatalogStatusAction::class)->execute($bmi, false, null);

        expect($bmi->fresh()->status->value)->toBe('inactive')
            ->and($doctor->insurers()->count())->toBe(1);
    });

    it('cambiar su slug deja el 301, con ámbito de país', function () {
        $bmi = insurer();
        insurer('BMI Seguros', 'GT');

        app(UpdateSlugAction::class)->execute($bmi, 'bmi');

        expect(SlugRedirect::where('entity_type', 'insurer')->where('entity_id', $bmi->id)->value('country_id'))->toBe(country()->id);
    });
});
