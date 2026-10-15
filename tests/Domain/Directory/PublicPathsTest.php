<?php

use App\Domain\Directory\Actions\DoctorSpecialtiesAction;
use App\Domain\Directory\Cdn\DoctorPublicPaths;
use App\Models\Specialty;

/*
| Rutas que se purgan del CDN al cambiar una ficha (ARQUITECTURA.md §8.1). Las
| subespecialidades no tienen páginas propias (§12 #2): se purgan las de su padre.
*/

it('incluye la ficha, la home del país, la página de cada especialidad y su listado en la ciudad principal', function () {
    $doctor = publishedDoctor();

    expect(DoctorPublicPaths::for($doctor))->toEqualCanonicalizing([
        '/costa-rica/medicos/ana-rojas',
        '/costa-rica',
        '/costa-rica/cardiologia',
        '/costa-rica/cardiologia/escazu',
    ]);
});

it('purga las páginas del padre por una subespecialidad, sin duplicados', function () {
    $child = Specialty::create(['parent_id' => specialty('cardiologia')->id, 'name' => 'Cardiología Intervencionista', 'slug' => 'cardiologia-intervencionista']);
    $doctor = publishedDoctor();
    app(DoctorSpecialtiesAction::class)->attach($doctor, $child, null);

    $paths = DoctorPublicPaths::for($doctor);

    expect($paths)->toContain('/costa-rica/cardiologia/escazu')
        ->and($paths)->not->toContain('/costa-rica/cardiologia-intervencionista/escazu')
        ->and($paths)->toBe(array_values(array_unique($paths)));
});
