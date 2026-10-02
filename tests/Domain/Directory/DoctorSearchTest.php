<?php

use App\Domain\Directory\Support\DoctorSearch;
use App\Models\Doctor;

/*
| Búsqueda del backoffice: sin acentos, en cualquier orden, sin títulos, y el colegiado
| cuando la palabra lleva dígitos.
*/

beforeEach(function () {
    $this->jose = makeDoctor(['first_name' => 'José María', 'last_name' => 'Pérez Rojas', 'license_number' => 'MED-4821']);
    $this->ana = makeDoctor(['first_name' => 'Ana', 'last_name' => 'Núñez', 'license_number' => 'COL-11842']);
});

function searchDoctors(string $search): array
{
    return DoctorSearch::apply(Doctor::query(), $search)->pluck('id')->all();
}

it('ignora acentos y mayúsculas', function () {
    expect(searchDoctors('jose perez'))->toBe([$this->jose->id])
        ->and(searchDoctors('NUNEZ'))->toBe([$this->ana->id]);
});

it('acepta las palabras en cualquier orden y exige todas', function () {
    expect(searchDoctors('Rojas José'))->toBe([$this->jose->id])
        ->and(searchDoctors('ana perez'))->toBe([]);
});

it('ignora los títulos', function () {
    expect(searchDoctors('Dra. Núñez'))->toBe([$this->ana->id]);
});

it('busca en el colegiado cuando la palabra tiene dígitos', function () {
    expect(searchDoctors('med-48'))->toBe([$this->jose->id])
        ->and(searchDoctors('11842'))->toBe([$this->ana->id])
        ->and(searchDoctors('ana 4821'))->toBe([]);
});

it('no trata los comodines de LIKE como comodines', function () {
    expect(searchDoctors('48%1'))->toBe([])
        ->and(searchDoctors('MED_4821'))->toBe([]);
});

it('una búsqueda sin palabras útiles no filtra, como la búsqueda vacía', function () {
    expect(searchDoctors('Dr. %'))->toHaveCount(2);
});
