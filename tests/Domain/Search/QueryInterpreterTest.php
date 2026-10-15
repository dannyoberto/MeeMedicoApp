<?php

use App\Domain\Search\QueryInterpreter;
use App\Domain\Search\SearchCriteria;
use App\Models\CityAlias;
use App\Models\Specialty;

/*
| Búsqueda en lenguaje natural: la frase del paciente se convierte en especialidad,
| ciudad e idioma, contra los catálogos y sus alias. Lo que sobra es el nombre.
*/

beforeEach(function () {
    city('CR', 'Heredia');
    city('CR', 'San José');
});

function interpret(string $query, string $countryCode = 'CR'): SearchCriteria
{
    return app(QueryInterpreter::class)->interpret(country($countryCode), $query);
}

it('entiende especialidad, ciudad e idioma en una frase', function () {
    $criteria = interpret('pediatra en Heredia que hable inglés');

    expect($criteria->specialty->slug)->toBe('pediatria')
        ->and($criteria->city->name)->toBe('Heredia')
        ->and($criteria->language->code)->toBe('en')
        ->and($criteria->text)->toBeNull()
        ->and($criteria->isListing())->toBeTrue();
});

it('ignora acentos y mayúsculas y acepta plurales y el orden inverso', function () {
    $criteria = interpret('SAN JOSE cardiologas');

    expect($criteria->specialty->slug)->toBe('cardiologia')
        ->and($criteria->city->name)->toBe('San José');
});

it('reconoce especialidades de varias palabras', function () {
    expect(interpret('ginecología y obstetricia en escazú')->specialty->slug)->toBe('ginecologia-y-obstetricia')
        ->and(interpret('médico general')->specialty->slug)->toBe('medicina-general');
});

it('lo que sobra es el nombre, en su forma original y sin palabras vacías', function () {
    $criteria = interpret('Dra. Pérez dermatóloga');

    expect($criteria->specialty->slug)->toBe('dermatologia')
        ->and($criteria->text)->toBe('Pérez');
});

it('solo reconoce ciudades del país de la búsqueda', function () {
    city('GT', 'Mixco');

    $criteria = interpret('cardiólogo en Mixco');

    expect($criteria->city)->toBeNull()
        ->and($criteria->text)->toBe('Mixco');
});

it('usa los alias de ciudad', function () {
    $sanJose = city('CR', 'San José');
    CityAlias::create(['city_id' => $sanJose->id, 'country_id' => $sanJose->country_id, 'alias' => 'Chepe', 'alias_normalized' => 'chepe']);

    expect(interpret('pediatra chepe')->city->is($sanJose))->toBeTrue();
});

it('coloca una subespecialidad bajo su especialidad padre', function () {
    Specialty::create(['parent_id' => specialty('cardiologia')->id, 'name' => 'Cardiología Intervencionista', 'slug' => 'cardiologia-intervencionista']);

    $criteria = interpret('cardiología intervencionista');

    expect($criteria->specialty->slug)->toBe('cardiologia')
        ->and($criteria->subspecialty->slug)->toBe('cardiologia-intervencionista');
});

it('no filtra por español: lo hablan todos', function () {
    $criteria = interpret('pediatra que hable español');

    expect($criteria->language)->toBeNull()
        ->and($criteria->text)->toBeNull();
});

it('una frase sin nada reconocible es una búsqueda por nombre', function () {
    $criteria = interpret('José Rojas');

    expect($criteria->specialty)->toBeNull()
        ->and($criteria->city)->toBeNull()
        ->and($criteria->text)->toBe('José Rojas');
});
