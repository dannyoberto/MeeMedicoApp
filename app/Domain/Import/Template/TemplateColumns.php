<?php

namespace App\Domain\Import\Template;

use App\Domain\Geo\Support\Normalize;
use App\Models\Country;

/**
 * Contrato de la plantilla de carga masiva: la única definición de sus columnas.
 * La usan el generador (TemplateWriter), el lector de import:ingest y los tests.
 *
 * Una fila por CONSULTORIO. Las filas con el mismo "ID del médico" son el mismo
 * médico: sus datos se toman de la primera fila del grupo, y si otra los contradice
 * la fila va a revisión (nunca se elige uno en silencio).
 *
 * Cambiar una clave o quitar una columna rompe los archivos ya distribuidos:
 * sube VERSION y haz que el lector acepte ambas mientras convivan.
 */
final class TemplateColumns
{
    public const VERSION = 1;

    /**
     * @return array<int, TemplateColumn>
     */
    public static function all(Country $country): array
    {
        $d = TemplateColumn::GROUP_DOCTOR;
        $l = TemplateColumn::GROUP_LOCATION;

        return [
            // ---------- Médico: se repite igual en cada fila del mismo médico ----------
            new TemplateColumn('doctor_ref', 'ID del médico', $d, required: true, text: true, width: 16,
                note: 'Identifica al médico en este archivo y agrupa sus consultorios. Usa el número de colegiado o un código propio (MED-001). Mantenlo igual en futuras cargas: así se actualiza la ficha en vez de duplicarla.'),
            new TemplateColumn('first_name', 'Nombres', $d, required: true, width: 20,
                note: 'Sin títulos (Dr., Dra.).'),
            new TemplateColumn('last_name', 'Apellidos', $d, required: true, width: 22,
                note: 'Ambos apellidos si los tiene.'),
            new TemplateColumn('professional_name', 'Nombre profesional', $d, width: 22,
                note: 'Opcional: cómo se presenta ("Dra. Ana Rojas").'),
            new TemplateColumn('gender', 'Género', $d, list: TemplateColumn::LIST_GENDERS, width: 14,
                note: 'Elige de la lista.'),
            new TemplateColumn('license_number', 'N.º de colegiado', $d, recommended: true, text: true, width: 16,
                note: 'Recomendado: es la clave más fiable para no duplicar fichas y el dato que se verifica contra el colegio médico.'),
            new TemplateColumn('specialty_1', 'Especialidad principal', $d, required: true, list: TemplateColumn::LIST_SPECIALTIES, width: 24,
                note: 'Elige de la lista. Si escribes otra, la fila irá a revisión.'),
            new TemplateColumn('specialty_2', 'Especialidad 2', $d, list: TemplateColumn::LIST_SPECIALTIES, width: 22,
                note: 'Opcional.'),
            new TemplateColumn('specialty_3', 'Especialidad 3', $d, list: TemplateColumn::LIST_SPECIALTIES, width: 22,
                note: 'Opcional.'),
            new TemplateColumn('languages', 'Idiomas', $d, width: 18,
                note: 'Separados por punto y coma: "Español; Inglés".'),
            new TemplateColumn('email', 'Correo', $d, width: 24,
                note: 'Correo de contacto público del médico.'),
            new TemplateColumn('website', 'Sitio web', $d, width: 24,
                note: 'Opcional.'),
            new TemplateColumn('headline', 'Titular', $d, width: 30,
                note: 'Una línea bajo el nombre: "Cardióloga intervencionista, 15 años de experiencia".'),
            new TemplateColumn('bio', 'Biografía', $d, width: 40,
                note: 'Opcional.'),
            new TemplateColumn('education', 'Formación', $d, width: 30,
                note: 'Opcional.'),
            new TemplateColumn('experience', 'Experiencia', $d, width: 30,
                note: 'Opcional.'),

            // ---------- Consultorio: uno por fila ----------
            new TemplateColumn('location_name', 'Nombre del lugar', $l, width: 26,
                note: 'Opcional: "Torre Médica Momentum, piso 4".'),
            new TemplateColumn('address', 'Dirección', $l, required: true, width: 32,
                note: 'Calle, número o señas.'),
            new TemplateColumn('address_2', 'Complemento', $l, width: 20,
                note: 'Opcional: consultorio, local, piso.'),
            new TemplateColumn('city', 'Ciudad', $l, required: true, list: TemplateColumn::LIST_CITIES, width: 20,
                note: 'Elige de la lista. Si no está, escríbela: se revisará y se añadirá como alias.'),
            new TemplateColumn('region', self::regionLabel($country), $l, recommended: true, list: TemplateColumn::LIST_REGIONS, width: 20,
                note: 'Recomendado: distingue ciudades con el mismo nombre (hay más de un "San José").'),
            new TemplateColumn('postal_code', 'Código postal', $l, text: true, width: 14,
                note: 'Opcional.'),
            new TemplateColumn('location_type', 'Tipo de consultorio', $l, list: TemplateColumn::LIST_LOCATION_TYPES, width: 18,
                note: 'Elige de la lista. Si se deja vacío: Consultorio.'),
            new TemplateColumn('phone', 'Teléfono', $l, recommended: true, text: true, width: 16,
                note: 'Recomendado: sin un contacto la ficha no se puede publicar. Escríbelo como lo darías; se guarda en formato internacional.'),
            new TemplateColumn('mobile', 'Móvil', $l, text: true, width: 16,
                note: 'Opcional.'),
            new TemplateColumn('whatsapp', 'WhatsApp', $l, text: true, width: 16,
                note: 'Opcional.'),
        ];
    }

    /**
     * Nombre de la división administrativa de primer nivel en cada país.
     */
    public static function regionLabel(Country $country): string
    {
        return match ($country->code) {
            'GT' => 'Departamento',
            'VE' => 'Estado',
            default => 'Provincia',
        };
    }

    /**
     * Clave de columna a partir de un encabezado del archivo. Tolera '*', mayúsculas,
     * acentos y espacios de más: "  CIUDAD * " → city.
     */
    public static function keyForHeader(Country $country, string $header): ?string
    {
        $wanted = Normalize::text(trim(str_replace('*', '', $header)));

        foreach (self::all($country) as $column) {
            if (Normalize::text($column->label) === $wanted) {
                return $column->key;
            }
        }

        return null;
    }
}
