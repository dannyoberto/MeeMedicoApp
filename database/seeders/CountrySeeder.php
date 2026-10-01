<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

/**
 * DATABASE.md §16.3. Todos inactivos: un país se activa cuando su carga está lista.
 *
 * firstOrCreate y no updateOrCreate: volver a sembrar no debe desactivar un país ya activado.
 */
class CountrySeeder extends Seeder
{
    public const COUNTRIES = [
        ['code' => 'CR', 'name' => 'Costa Rica', 'dial_code' => '+506', 'slug' => 'costa-rica'],
        ['code' => 'GT', 'name' => 'Guatemala', 'dial_code' => '+502', 'slug' => 'guatemala'],
        ['code' => 'DO', 'name' => 'República Dominicana', 'dial_code' => '+1809', 'slug' => 'republica-dominicana'],
        ['code' => 'VE', 'name' => 'Venezuela', 'dial_code' => '+58', 'slug' => 'venezuela'],
    ];

    public function run(): void
    {
        foreach (self::COUNTRIES as $country) {
            Country::firstOrCreate(['code' => $country['code']], $country);
        }
    }
}
