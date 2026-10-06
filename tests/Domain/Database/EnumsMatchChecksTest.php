<?php

use Illuminate\Support\Facades\DB;

/*
| Cada enum de PHP declara en su docblock el CHECK que refleja ("Valores: deben
| coincidir con el CHECK x"). Si alguien añade un estado en un lado y no en el otro,
| este test falla antes de que lo haga producción.
*/

$enums = collect(glob(__DIR__.'/../../../app/Domain/*/Enums/*.php'))->mapWithKeys(function (string $file) {
    $source = file_get_contents($file);
    preg_match('/^namespace (.+);/m', $source, $namespace);
    preg_match('/CHECK (\w+)/', $source, $check);

    return [$namespace[1].'\\'.basename($file, '.php') => $check[1]];
});

it('coincide con su CHECK de la base', function (string $enum, string $check) {
    $definition = DB::scalar('select pg_get_constraintdef(oid) from pg_constraint where conname = ?', [$check]);
    expect($definition)->not->toBeNull("No existe el CHECK {$check}");

    preg_match_all("/'([a-z_]+)'::/", $definition, $matches);
    $inDatabase = collect($matches[1])->sort()->values()->all();
    $inPhp = collect($enum::cases())->map(fn ($case) => $case->value)->sort()->values()->all();

    expect($inPhp)->toBe($inDatabase);
})->with($enums->map(fn ($check, $enum) => [$enum, $check])->values()->all());

it('cubre los 35 enums del dominio', fn () => expect($enums)->toHaveCount(35));
