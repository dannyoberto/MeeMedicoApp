<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Geografía de Venezuela (DATABASE.md §16.3). No va en DatabaseSeeder: se siembra por país,
 * antes de su primer import: php artisan db:seed --class=VenezuelaGeographySeeder
 *
 * Estados: las 24 entidades federales (Vargas es La Guaira desde 2019).
 * Ciudades: PARCIAL. Solo las que usa la carga demo; falta la lista completa con fuente
 * oficial (INE) antes de la carga real. Caracas es una sola ciudad en Distrito Capital,
 * aunque el área metropolitana cruce a Miranda: es la búsqueda que hace el paciente.
 *
 * firstOrCreate por slug: volver a sembrar no pisa las correcciones del backoffice.
 */
class VenezuelaGeographySeeder extends Seeder
{
    /** Estado => ciudades */
    public const GEOGRAPHY = [
        'Amazonas' => [],
        'Anzoátegui' => ['Barcelona', 'Lechería', 'Puerto La Cruz'],
        'Apure' => [],
        'Aragua' => ['Cagua', 'Maracay', 'Turmero', 'Villa de Cura'],
        'Barinas' => ['Barinas'],
        'Bolívar' => ['Ciudad Bolívar', 'Puerto Ordaz', 'San Félix'],
        'Carabobo' => ['Guacara', 'Naguanagua', 'San Diego', 'Valencia'],
        'Cojedes' => [],
        'Delta Amacuro' => [],
        'Distrito Capital' => ['Caracas'],
        'Falcón' => ['Coro', 'Punta Cardón', 'Punto Fijo', 'Tucacas'],
        'Guárico' => [],
        'La Guaira' => ['Catia La Mar', 'La Guaira', 'Maiquetía'],
        'Lara' => ['Barquisimeto', 'Cabudare'],
        'Mérida' => ['Ejido', 'El Vigía', 'Mérida'],
        'Miranda' => ['Carrizal', 'Los Teques', 'San Antonio de los Altos'],
        'Monagas' => ['Maturín', 'Punta de Mata'],
        'Nueva Esparta' => ['Juan Griego', 'La Asunción', 'Los Robles', 'Pampatar', 'Porlamar'],
        'Portuguesa' => [],
        'Sucre' => ['Carúpano', 'Cumaná'],
        'Táchira' => ['Colón', 'Rubio', 'San Cristóbal', 'Táriba'],
        'Trujillo' => ['Trujillo', 'Valera'],
        'Yaracuy' => [],
        'Zulia' => ['Cabimas', 'Ciudad Ojeda', 'Los Puertos de Altagracia', 'Maracaibo'],
    ];

    public function run(): void
    {
        $country = Country::where('code', 'VE')->firstOrFail();

        foreach (self::GEOGRAPHY as $regionName => $cities) {
            $region = Region::firstOrCreate(
                ['country_id' => $country->getKey(), 'slug' => Str::slug($regionName)],
                ['name' => $regionName],
            );

            foreach ($cities as $cityName) {
                City::firstOrCreate(
                    ['country_id' => $country->getKey(), 'slug' => Str::slug($cityName)],
                    ['region_id' => $region->getKey(), 'name' => $cityName],
                );
            }
        }
    }
}
