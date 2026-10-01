<?php

use App\Domain\Directory\Actions\UpdateSlugAction;
use App\Domain\Directory\Cdn\PurgeCdnPaths;
use App\Domain\Directory\Support\DoctorSlugGenerator;
use App\Models\Region;
use App\Models\SlugRedirect;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

describe('generador de slugs de médico', function () {
    it('sigue la secuencia nombre → nombre-especialidad → nombre-especialidad-N', function () {
        $cr = country()->id;
        makeDoctor(['first_name' => 'Juan', 'last_name' => 'Pérez']);
        expect(DoctorSlugGenerator::generate($cr, 'Juan', 'Pérez', specialty()))->toBe('juan-perez-cardiologia');

        makeDoctor(['first_name' => 'Juan', 'last_name' => 'Pérez M'])->forceFill(['slug' => 'juan-perez-cardiologia'])->save();
        expect(DoctorSlugGenerator::generate($cr, 'Juan', 'Pérez', specialty()))->toBe('juan-perez-cardiologia-2');
    });

    it('sin especialidad desambigua con número', function () {
        makeDoctor(['first_name' => 'Juan', 'last_name' => 'Pérez']);

        expect(makeDoctor(['first_name' => 'Juan', 'last_name' => 'Perez'])->slug)->toBe('juan-perez-2');
    });

    it('quita títulos y acentos', function () {
        expect(DoctorSlugGenerator::base('Dra. María José Pérez'))->toBe('maria-jose-perez');
    });

    it('nunca produce un slug reservado', function () {
        expect(makeDoctor(['first_name' => 'Medicos', 'last_name' => ''])->slug)->not->toBe('medicos');
    });

    it('es único por país, no global', function () {
        makeDoctor(['first_name' => 'Juan', 'last_name' => 'Pérez']);

        expect(makeDoctor(['country_id' => country('DO')->id, 'first_name' => 'Juan', 'last_name' => 'Pérez'])->slug)->toBe('juan-perez');
    });

    it('no reutiliza la URL antigua de otro médico', function () {
        $first = makeDoctor(['first_name' => 'Juan', 'last_name' => 'Pérez']);
        app(UpdateSlugAction::class)->execute($first, 'juan-perez-mora');

        expect(makeDoctor(['first_name' => 'Juan', 'last_name' => 'Pérez'])->slug)->toBe('juan-perez-2');
    });
});

describe('UpdateSlugAction (AGENTS.md §3: nunca sin 301)', function () {
    it('escribe el redirect antes de cambiar el slug y lo audita', function () {
        $specialty = specialty();
        app(UpdateSlugAction::class)->execute($specialty, 'cardiologia-clinica');

        expect($specialty->fresh()->slug)->toBe('cardiologia-clinica')
            ->and(SlugRedirect::where('entity_type', 'specialty')->where('old_slug', 'cardiologia')->value('entity_id'))->toBe($specialty->id)
            ->and(Activity::where('subject_id', $specialty->id)->where('event', 'slug.updated')->exists())->toBeTrue();
    });

    it('rechaza slugs reservados, de país, mal formados o ya usados en su ámbito', function (string $slug) {
        expect(fn () => app(UpdateSlugAction::class)->execute(specialty(), $slug))->toThrow(ValidationException::class);
    })->with(['medicos', 'costa-rica', 'Cardio Logía', 'pediatria']);

    it('volver a un slug anterior elimina su redirect para que no resuelva dos veces', function () {
        $specialty = specialty();
        app(UpdateSlugAction::class)->execute($specialty, 'cardiologia-clinica');
        app(UpdateSlugAction::class)->execute($specialty->fresh(), 'cardiologia');

        expect(SlugRedirect::where('old_slug', 'cardiologia')->exists())->toBeFalse()
            ->and(SlugRedirect::where('old_slug', 'cardiologia-clinica')->where('entity_id', $specialty->id)->exists())->toBeTrue();
    });

    it('regiones y ciudades son únicas por país', function () {
        Region::create(['country_id' => country('CR')->id, 'name' => 'San José', 'slug' => 'san-jose']);
        $gt = Region::create(['country_id' => country('GT')->id, 'name' => 'G', 'slug' => 'g']);
        app(UpdateSlugAction::class)->execute($gt, 'san-jose');
        expect($gt->fresh()->slug)->toBe('san-jose');

        $cr2 = Region::create(['country_id' => country('CR')->id, 'name' => 'X', 'slug' => 'x-cr']);
        expect(fn () => app(UpdateSlugAction::class)->execute($cr2, 'san-jose'))->toThrow(ValidationException::class);
    });

    it('en un médico publicado purga la URL anterior y la nueva', function () {
        $doctor = publishedDoctor();
        Bus::fake([PurgeCdnPaths::class]);

        app(UpdateSlugAction::class)->execute($doctor, 'ana-rojas-cardiologa');

        Bus::assertDispatched(PurgeCdnPaths::class, fn ($job) => in_array('/costa-rica/medicos/ana-rojas', $job->paths)
            && in_array('/costa-rica/medicos/ana-rojas-cardiologa', $job->paths));
    });

    it('un médico no puede quedarse con la URL antigua de otro médico', function () {
        $a = makeDoctor(['first_name' => 'Ana', 'last_name' => 'Rojas']);
        app(UpdateSlugAction::class)->execute($a, 'ana-rojas-mendez');
        $b = makeDoctor(['first_name' => 'Luis', 'last_name' => 'Soto']);

        expect(fn () => app(UpdateSlugAction::class)->execute($b, 'ana-rojas'))->toThrow(ValidationException::class);
    });
});
