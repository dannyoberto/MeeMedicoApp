<?php

use App\Domain\Directory\Support\NameNormalizer;
use App\Domain\Directory\Support\PhoneNormalizer;
use App\Domain\Geo\Support\Normalize;

/*
| Claves de deduplicación: el importador y el backoffice deben producir exactamente
| la misma forma para el mismo dato (DATABASE.md §3.6, §12.3).
*/

it('normaliza teléfonos de los cuatro países a E.164', function (string $raw, string $country, string $e164) {
    expect(PhoneNormalizer::toE164($raw, $country))->toBe($e164);
})->with([
    'Costa Rica' => ['2222-3333', 'CR', '+50622223333'],
    'Guatemala' => ['2345-6789', 'GT', '+50223456789'],
    'RD código 829' => ['(829) 555-1234', 'DO', '+18295551234'],
    'RD código 809' => ['809-555-1234', 'DO', '+18095551234'],
    'Venezuela móvil' => ['0412-555-1234', 'VE', '+584125551234'],
    'ya internacional' => ['+506 2222 3333', 'CR', '+50622223333'],
]);

it('nunca inventa un teléfono: lo inválido es null', function (string $raw) {
    expect(PhoneNormalizer::toE164($raw, 'CR'))->toBeNull();
})->with(['123', 'no es un número', '']);

it('la clave de nombre ignora títulos, acentos, puntuación y orden', function (string $name) {
    expect(NameNormalizer::normalize($name))->toBe('jose maria perez');
})->with(['Dra. María José Pérez', 'PEREZ, Maria Jose', 'Dr José   María Pérez', 'Lic. maría josé PÉREZ']);

it('el texto normalizado va en minúsculas, sin acentos y con espacios colapsados', function () {
    expect(Normalize::text('  San  José, Escazú '))->toBe('san jose, escazu');
});
