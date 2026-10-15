<?php

namespace Database\Seeders;

use App\Domain\Directory\Enums\SpecialtyAliasKind;
use App\Domain\Geo\Support\Normalize;
use App\Models\Specialty;
use App\Models\SpecialtyAlias;
use Illuminate\Database\Seeder;

/**
 * Catálogo BASE de especialidades, pendiente de revisión médica y SEO (DATABASE.md §16.3):
 * el catálogo es la autoridad y los alias el puente; nunca se genera desde un import.
 *
 * Sin subespecialidades (parent_id) todavía. Cuando las haya, no tienen página propia:
 * son un filtro (?sub=) dentro del listado de su padre (§8.1).
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
        'cirugia-cardiovascular' => 'Cirugía Cardiovascular',
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
        'medicina-de-emergencias' => 'Medicina de Emergencias',
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

    /**
     * Cómo busca la gente: por el profesional, no por la disciplina. Alias 'seo' que usa
     * QueryInterpreter ("pediatra en Heredia"). Los plurales no hacen falta: el
     * intérprete prueba también la forma sin -s final.
     */
    public const SEO_ALIASES = [
        'alergologia' => ['alergólogo', 'alergóloga', 'alergista'],
        'anestesiologia' => ['anestesiólogo', 'anestesióloga', 'anestesista'],
        'cardiologia' => ['cardiólogo', 'cardióloga', 'médico del corazón'],
        'cirugia-cardiovascular' => ['cirujano cardiovascular', 'cirujana cardiovascular'],
        'cirugia-general' => ['cirujano', 'cirujana', 'cirujano general', 'cirujana general'],
        'cirugia-plastica' => ['cirujano plástico', 'cirujana plástica'],
        'cirugia-vascular' => ['cirujano vascular', 'cirujana vascular'],
        'dermatologia' => ['dermatólogo', 'dermatóloga'],
        'endocrinologia' => ['endocrinólogo', 'endocrinóloga', 'endocrino'],
        'gastroenterologia' => ['gastroenterólogo', 'gastroenteróloga'],
        'geriatria' => ['geriatra'],
        'ginecologia-y-obstetricia' => ['ginecólogo', 'ginecóloga', 'ginecología', 'obstetra', 'obstetricia'],
        'hematologia' => ['hematólogo', 'hematóloga'],
        'infectologia' => ['infectólogo', 'infectóloga'],
        'medicina-de-emergencias' => ['emergenciólogo', 'emergencióloga', 'emergencias'],
        'medicina-familiar' => ['médico familiar', 'médica familiar', 'médico de familia'],
        'medicina-fisica-y-rehabilitacion' => ['fisiatra', 'fisiatría'],
        'medicina-general' => ['médico general', 'médica general'],
        'medicina-interna' => ['internista', 'médico internista'],
        'nefrologia' => ['nefrólogo', 'nefróloga'],
        'neumologia' => ['neumólogo', 'neumóloga'],
        'neurocirugia' => ['neurocirujano', 'neurocirujana'],
        'neurologia' => ['neurólogo', 'neuróloga'],
        'oftalmologia' => ['oftalmólogo', 'oftalmóloga', 'oculista'],
        'oncologia' => ['oncólogo', 'oncóloga'],
        'ortopedia-y-traumatologia' => ['ortopedista', 'ortopeda', 'ortopedia', 'traumatólogo', 'traumatóloga', 'traumatología'],
        'otorrinolaringologia' => ['otorrino', 'otorrinolaringólogo', 'otorrinolaringóloga'],
        'pediatria' => ['pediatra'],
        'psiquiatria' => ['psiquiatra'],
        'radiologia' => ['radiólogo', 'radióloga'],
        'reumatologia' => ['reumatólogo', 'reumatóloga'],
        'urologia' => ['urólogo', 'uróloga'],
    ];

    public function run(): void
    {
        foreach (self::SPECIALTIES as $slug => $name) {
            Specialty::firstOrCreate(['slug' => $slug], ['name' => $name]);
        }

        $specialties = Specialty::whereIn('slug', array_keys(self::SEO_ALIASES))->pluck('id', 'slug');

        foreach (self::SEO_ALIASES as $slug => $aliases) {
            foreach ($aliases as $alias) {
                // Si el alias ya existe (p. ej. mapeado desde un import), no se toca.
                SpecialtyAlias::firstOrCreate(
                    ['alias_normalized' => Normalize::text($alias)],
                    ['specialty_id' => $specialties[$slug], 'alias' => $alias, 'kind' => SpecialtyAliasKind::Seo],
                );
            }
        }
    }
}
