<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seeds de Fase 1 (DATABASE.md §16). Todos son idempotentes.
     *
     * Regiones y ciudades se siembran por país antes de su primer import, con fuente oficial.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            CountrySeeder::class,
            LanguageSeeder::class,
            SpecialtySeeder::class,
        ]);
    }
}
