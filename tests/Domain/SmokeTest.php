<?php

use App\Models\Country;
use App\Models\Role;

it('migra y siembra el esquema de Fase 1 en PostgreSQL', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(Country::count())->toBe(4)
        ->and(Role::pluck('name')->sort()->values()->all())->toBe(['admin', 'doctor']);
});
