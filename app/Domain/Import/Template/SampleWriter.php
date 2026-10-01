<?php

namespace App\Domain\Import\Template;

use App\Models\Country;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Plantilla llena con datos SINTÉTICOS (personas inventadas) para probar el pipeline
 * de punta a punta. Incluye a propósito los casos difíciles de una fuente real, para
 * ver cómo responde cada etapa. Nunca se usa con datos reales.
 */
class SampleWriter
{
    /** Ciudades y regiones de ejemplo por país: [ciudad, región]. */
    private const PLACES = [
        'CR' => [['San José', 'San José'], ['Escazú', 'San José'], ['Heredia', 'Heredia'], ['Cartago', 'Cartago']],
        'GT' => [['Ciudad de Guatemala', 'Guatemala'], ['Mixco', 'Guatemala'], ['Quetzaltenango', 'Quetzaltenango'], ['Antigua Guatemala', 'Sacatepéquez']],
        'DO' => [['Santo Domingo', 'Distrito Nacional'], ['Santiago de los Caballeros', 'Santiago'], ['La Romana', 'La Romana'], ['Punta Cana', 'La Altagracia']],
        'VE' => [['Caracas', 'Distrito Capital'], ['Maracaibo', 'Zulia'], ['Valencia', 'Carabobo'], ['Barquisimeto', 'Lara']],
    ];

    /** Formato de teléfono válido por país: fijo y móvil (%04d = 4 dígitos). */
    private const PHONES = [
        'CR' => ['2222-%04d', '8888-%04d'],
        'GT' => ['2333-%04d', '5555-%04d'],
        'DO' => ['809-555-%04d', '829-555-%04d'],
        'VE' => ['0212-555-%04d', '0412-555-%04d'],
    ];

    private const FIRST = ['Ana', 'Luis', 'María', 'Carlos', 'Sofía', 'Jorge', 'Lucía', 'Andrés', 'Valeria', 'Diego', 'Camila', 'Ricardo', 'Elena', 'Fernando', 'Paula', 'Miguel'];

    private const LAST = ['Rojas Méndez', 'Soto Vargas', 'Jiménez Mora', 'Castro Ruiz', 'Herrera León', 'Navarro Gil', 'Campos Ortiz', 'Vega Ramos', 'Salas Pineda', 'Araya Brenes', 'Quesada Lobo', 'Mena Solís', 'Chaves Arias', 'Porras Umaña'];

    private const SPECIALTIES = ['Cardiología', 'Pediatría', 'Dermatología', 'Medicina General', 'Ginecología y Obstetricia', 'Oftalmología', 'Psiquiatría', 'Neurología', 'Urología', 'Medicina Interna'];

    /**
     * @return array<int, string> descripción de cada caso difícil incluido (para mostrarla al usuario)
     */
    public function write(Country $country, string $path): array
    {
        app(TemplateWriter::class)->write($country, $path);

        [$rows, $cases] = $this->rows($country);

        $book = IOFactory::load($path);
        $sheet = $book->getSheetByName(TemplateWriter::SHEET_DATA);
        $columns = TemplateColumns::all($country);

        foreach ($rows as $r => $row) {
            foreach ($columns as $i => $column) {
                if (isset($row[$column->key])) {
                    $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($i + 1).($r + 2), $row[$column->key], 'str');
                }
            }
        }

        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $cases;
    }

    /**
     * @return array{0: array<int, array<string, string>>, 1: array<int, string>}
     */
    private function rows(Country $country): array
    {
        $places = self::PLACES[$country->code] ?? self::PLACES['CR'];
        [$landline, $mobile] = self::PHONES[$country->code] ?? self::PHONES['CR'];
        $region = TemplateColumns::regionLabel($country);
        $rows = [];

        // 22 médicos "limpios" para que el lote tenga volumen.
        for ($i = 0; $i < 22; $i++) {
            [$city, $reg] = $places[$i % count($places)];
            $rows[] = [
                'doctor_ref' => sprintf('MED-%03d', $i + 1),
                'first_name' => self::FIRST[$i % count(self::FIRST)],
                'last_name' => self::LAST[($i * 3) % count(self::LAST)],
                'gender' => $i % 2 ? 'Masculino' : 'Femenino',
                'license_number' => sprintf('LIC-%04d', 1000 + $i),
                'specialty_1' => self::SPECIALTIES[$i % count(self::SPECIALTIES)],
                'specialty_2' => $i % 4 === 0 ? 'Medicina General' : null,
                'languages' => $i % 3 === 0 ? 'Español; Inglés' : 'Español',
                'headline' => self::SPECIALTIES[$i % count(self::SPECIALTIES)].' con atención en '.$city,
                'location_name' => $i % 5 === 0 ? 'Torre Médica Central, piso '.($i % 7 + 1) : null,
                'address' => 'Avenida '.($i + 1).', edificio '.(100 + $i),
                'city' => $city,
                'region' => $reg,
                'location_type' => $i % 3 === 0 ? 'Clínica' : 'Consultorio',
                'phone' => sprintf($landline, 1000 + $i),
                'whatsapp' => $i % 2 ? sprintf($mobile, 2000 + $i) : null,
            ];
        }

        [$c1, $r1] = $places[0];
        [$c2, $r2] = $places[1];
        $cases = [];

        // Un médico con dos consultorios: mismo ID, dos filas.
        $rows[] = ['doctor_ref' => 'MED-101', 'first_name' => 'Ana', 'last_name' => 'Rojas Méndez', 'license_number' => 'LIC-5001', 'specialty_1' => 'Cardiología',
            'email' => 'ana.rojas@ejemplo.com', 'languages' => 'Español; Inglés', 'address' => 'Calle 1, local 4', 'city' => $c1, 'region' => $r1, 'phone' => sprintf($landline, 5001)];
        $rows[] = ['doctor_ref' => 'MED-101', 'address' => 'Plaza Norte, consultorio 12', 'city' => $c2, 'region' => $r2, 'location_type' => 'Clínica', 'phone' => sprintf($landline, 5002)];
        $cases[] = 'MED-101: un médico con dos consultorios (dos filas con el mismo ID) → una ficha con dos ubicaciones.';

        $rows[] = ['doctor_ref' => 'MED-102', 'first_name' => 'Dr. Carlos', 'last_name' => 'Méndez Arce', 'license_number' => 'LIC-5002', 'specialty_1' => 'CARDIOLOGIA',
            'address' => 'Barrio Sur 8', 'city' => $c1, 'region' => $r1, 'phone' => sprintf($landline, 5003)];
        $cases[] = 'MED-102: título en el nombre y especialidad en mayúsculas sin tilde → se normalizan solos.';

        $rows[] = ['doctor_ref' => 'MED-103', 'first_name' => 'Lucía', 'last_name' => 'Brenes Mora', 'specialty_1' => 'Cardiología Clínica',
            'address' => 'Calle 9', 'city' => $c1, 'region' => $r1, 'phone' => sprintf($landline, 5004)];
        $cases[] = 'MED-103: especialidad "Cardiología Clínica" que no está en el catálogo → a revisión; asígnala a Cardiología y se guarda el alias.';

        $rows[] = ['doctor_ref' => 'MED-104', 'first_name' => 'Jorge', 'last_name' => 'Ulate Vargas', 'specialty_1' => 'Pediatría',
            'address' => 'Avenida 2', 'city' => 'Sn. '.$c1, 'region' => $r1, 'phone' => sprintf($landline, 5005)];
        $cases[] = "MED-104: ciudad mal escrita (\"Sn. {$c1}\") → a revisión; asígnala y se guarda el alias.";

        $rows[] = ['doctor_ref' => 'MED-105', 'first_name' => 'Elena', 'last_name' => 'Pineda Ríos', 'specialty_1' => 'Dermatología',
            'address' => 'Calle 3', 'city' => $c2, 'region' => $r2, 'phone' => '123', 'email' => 'correo-sin-arroba'];
        $cases[] = 'MED-105: teléfono y correo inválidos → la ficha se crea sin ellos y queda un aviso (no se podrá publicar sin contacto).';

        $rows[] = ['doctor_ref' => 'MED-106', 'first_name' => 'Juan', 'last_name' => 'Pérez Soto', 'license_number' => 'LIC-6001', 'specialty_1' => 'Urología',
            'address' => 'Avenida 10', 'city' => $c1, 'region' => $r1, 'phone' => sprintf($landline, 6001)];
        $rows[] = ['doctor_ref' => 'MED-107', 'first_name' => 'Juan', 'last_name' => 'Pérez Soto', 'license_number' => 'LIC-6002', 'specialty_1' => 'Pediatría',
            'address' => 'Calle 20', 'city' => $c2, 'region' => $r2, 'phone' => sprintf($landline, 6002)];
        $cases[] = 'MED-106 y MED-107: homónimos con distinto colegiado → dos fichas distintas (nunca se fusionan por nombre).';

        $rows[] = ['doctor_ref' => 'MED-108', 'first_name' => 'Paula', 'last_name' => 'Arias Gómez', 'license_number' => 'LIC-7000', 'specialty_1' => 'Neurología',
            'address' => 'Calle 30', 'city' => $c1, 'region' => $r1, 'phone' => sprintf($landline, 7001)];
        $rows[] = ['doctor_ref' => 'MED-109', 'first_name' => 'Pablo', 'last_name' => 'Arias Gómez', 'license_number' => 'LIC-7000', 'specialty_1' => 'Neurología',
            'address' => 'Calle 31', 'city' => $c1, 'region' => $r1, 'phone' => sprintf($landline, 7002)];
        $cases[] = 'MED-108 y MED-109: el mismo colegiado en dos médicos distintos → ambos a revisión (uno de los dos está mal).';

        $rows[] = ['doctor_ref' => 'MED-110', 'first_name' => 'Miguel', 'last_name' => 'Salas Mena', 'specialty_1' => 'Psiquiatría',
            'address' => 'Avenida 40', 'city' => $c1, 'region' => $r1, 'phone' => sprintf($landline, 8001)];
        $rows[] = ['doctor_ref' => 'MED-110', 'last_name' => 'Salas Mora', 'address' => 'Avenida 41', 'city' => $c2, 'region' => $r2, 'phone' => sprintf($landline, 8002)];
        $cases[] = 'MED-110: sus dos filas no coinciden en el apellido → a revisión; si apruebas, se usa el de la primera fila.';

        $rows[] = ['doctor_ref' => 'MED-111', 'first_name' => 'Valeria', 'last_name' => 'Lobo Quirós', 'specialty_1' => 'Oftalmología',
            'city' => $c1, 'region' => $r1, 'phone' => sprintf($landline, 9001)];
        $cases[] = 'MED-111: falta la dirección (obligatoria) → a revisión; hay que corregir el archivo o descartarla.';

        $cases[] = "Ciudades usadas: {$places[0][0]}, {$places[1][0]}, {$places[2][0]} y {$places[3][0]}. Si no existen en Plataforma → Ciudades, irán a revisión: asígnalas una vez y el alias resolverá las demás filas.";
        $cases[] = 'Supresiones: registra antes una supresión con el colegiado LIC-1005 para ver que esa persona no se vuelve a crear.';

        return [array_map(fn ($row) => array_filter($row, fn ($v) => $v !== null), $rows), $cases];
    }
}
