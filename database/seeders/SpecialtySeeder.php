<?php

namespace Database\Seeders;

use App\Models\Specialty;
use Illuminate\Database\Seeder;

/**
 * Catálogo BASE de especialidades, pendiente de revisión médica y SEO (DATABASE.md §16.3):
 * el catálogo es la autoridad y los alias el puente; nunca se genera desde un import.
 *
 * Sin subespecialidades (parent_id): su esquema de URL es una decisión pendiente (§8.1).
 * El slug viaja en la URL /{pais}/{especialidad}/{ciudad}, así que cambiarlo después
 * de indexado exige UpdateSlugAction y su 301.
 *
 * firstOrCreate: volver a sembrar no pisa las correcciones hechas desde el backoffice.
 */
class SpecialtySeeder extends Seeder
{
    public const SPECIALTIES = [
        'alergologia' => 'Alergología',
        'anestesiologia' => 'Anestesiología',
        'cardiologia' => 'Cardiología',
        'cirugia-general' => 'Cirugía General',
        'cirugia-plastica' => 'Cirugía Plástica',
        'cirugia-vascular' => 'Cirugía Vascular',
        'dermatologia' => 'Dermatología',
        'endocrinologia' => 'Endocrinología',
        'gastroenterologia' => 'Gastroenterología',
        'geriatria' => 'Geriatría',
        'ginecologia-y-obstetricia' => 'Ginecología y Obstetricia',
        'hematologia' => 'Hematología',
        'infectologia' => 'Infectología',
        'medicina-familiar' => 'Medicina Familiar',
        'medicina-fisica-y-rehabilitacion' => 'Medicina Física y Rehabilitación',
        'medicina-general' => 'Medicina General',
        'medicina-interna' => 'Medicina Interna',
        'nefrologia' => 'Nefrología',
        'neumologia' => 'Neumología',
        'neurocirugia' => 'Neurocirugía',
        'neurologia' => 'Neurología',
        'oftalmologia' => 'Oftalmología',
        'oncologia' => 'Oncología',
        'ortopedia-y-traumatologia' => 'Ortopedia y Traumatología',
        'otorrinolaringologia' => 'Otorrinolaringología',
        'pediatria' => 'Pediatría',
        'psiquiatria' => 'Psiquiatría',
        'radiologia' => 'Radiología',
        'reumatologia' => 'Reumatología',
        'urologia' => 'Urología',
    ];

    public function run(): void
    {
        foreach (self::SPECIALTIES as $slug => $name) {
            Specialty::firstOrCreate(['slug' => $slug], ['name' => $name]);
        }
    }
}
