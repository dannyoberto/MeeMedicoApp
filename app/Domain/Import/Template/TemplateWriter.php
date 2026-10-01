<?php

namespace App\Domain\Import\Template;

use App\Domain\Directory\Enums\DoctorGender;
use App\Domain\Directory\Enums\LocationType;
use App\Domain\Directory\Enums\SpecialtyStatus;
use App\Domain\Geo\Enums\CityStatus;
use App\Domain\Geo\Enums\RegionStatus;
use App\Models\Country;
use App\Models\Specialty;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Genera la plantilla Excel de carga masiva de un país. Las listas desplegables se
 * leen de la base al generar: la plantilla siempre refleja el catálogo vigente.
 */
class TemplateWriter
{
    public const SHEET_DATA = 'Médicos';

    public const SHEET_HELP = 'Instrucciones';

    public const SHEET_EXAMPLE = 'Ejemplo';

    public const SHEET_LISTS = 'Listas';

    public const SHEET_META = '_meta';

    /** Filas con formato y listas desplegables preparadas. */
    public const ROWS = 5000;

    // Colores de resources/css/tokens.css (design-system.md §4).
    private const PETROL = '1B5E6F';

    private const PETROL_SOFT = 'EEF5F6';

    private const GRAY = 'EDF1F3';

    private const INK = '1A2328';

    public function write(Country $country, string $path): string
    {
        $columns = TemplateColumns::all($country);
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()->setCreator('MeeMedico')->setTitle("Plantilla de médicos · {$country->name}");

        $data = $spreadsheet->getActiveSheet()->setTitle(self::SHEET_DATA);
        $this->header($data, $columns);
        $this->prepareRows($data, $columns);

        $this->lists($spreadsheet, $country);
        $this->validations($data, $columns);

        $this->instructions($spreadsheet->createSheet()->setTitle(self::SHEET_HELP), $country, $columns);
        $example = $spreadsheet->createSheet()->setTitle(self::SHEET_EXAMPLE);
        $this->header($example, $columns);
        $this->example($example, $country, $columns);

        $meta = $spreadsheet->createSheet()->setTitle(self::SHEET_META);
        $meta->fromArray([
            ['template_version', TemplateColumns::VERSION],
            ['country', $country->code],
            ['generated_at', now()->toIso8601String()],
        ]);
        $meta->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);

        $spreadsheet->setActiveSheetIndex(0);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    /**
     * @param  array<int, TemplateColumn>  $columns
     */
    private function header(Worksheet $sheet, array $columns): void
    {
        foreach ($columns as $i => $column) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $cell = "{$letter}1";
            $sheet->setCellValue($cell, $column->header());
            $sheet->getColumnDimension($letter)->setWidth($column->width);

            $doctor = $column->group === TemplateColumn::GROUP_DOCTOR;
            $style = $sheet->getStyle($cell);
            $style->getFont()->setBold(true)->getColor()->setRGB($doctor ? 'FFFFFF' : self::INK);
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($doctor ? self::PETROL : self::GRAY);
            $style->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);

            $prefix = $column->required ? 'Obligatorio. ' : ($column->recommended ? 'Recomendado. ' : '');
            $sheet->getComment($cell)->setWidth('260pt')->setHeight('90pt')->getText()->createTextRun($prefix.$column->note);
        }

        $sheet->getRowDimension(1)->setRowHeight(34);
        $sheet->freezePane('D2');
        $sheet->setAutoFilter('A1:'.Coordinate::stringFromColumnIndex(count($columns)).'1');
    }

    /**
     * @param  array<int, TemplateColumn>  $columns
     */
    private function prepareRows(Worksheet $sheet, array $columns): void
    {
        $last = self::ROWS + 1;

        foreach ($columns as $i => $column) {
            if ($column->text) {
                $letter = Coordinate::stringFromColumnIndex($i + 1);
                $sheet->getStyle("{$letter}2:{$letter}{$last}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            }
        }
    }

    private function lists(Spreadsheet $spreadsheet, Country $country): void
    {
        $sheet = $spreadsheet->createSheet()->setTitle(self::SHEET_LISTS);

        $lists = [
            TemplateColumn::LIST_SPECIALTIES => Specialty::where('status', SpecialtyStatus::Active)->orderBy('name')->pluck('name')->all(),
            TemplateColumn::LIST_GENDERS => array_map(fn ($g) => $g->getLabel(), DoctorGender::cases()),
            TemplateColumn::LIST_LOCATION_TYPES => array_map(fn ($t) => $t->getLabel(), LocationType::cases()),
            TemplateColumn::LIST_CITIES => $country->cities()->where('status', CityStatus::Active)->orderBy('name')->pluck('name')->unique()->values()->all(),
            TemplateColumn::LIST_REGIONS => $country->regions()->where('status', RegionStatus::Active)->orderBy('name')->pluck('name')->all(),
        ];

        $col = 1;
        foreach ($lists as $name => $values) {
            $letter = Coordinate::stringFromColumnIndex($col++);
            $sheet->setCellValue("{$letter}1", $name);
            foreach (array_values($values) as $row => $value) {
                $sheet->setCellValueExplicit($letter.($row + 2), $value, 'str');
            }

            if ($values !== []) {
                $range = "\${$letter}\$2:\${$letter}\$".(count($values) + 1);
                $spreadsheet->addNamedRange(new NamedRange("lista_{$name}", $sheet, $range));
            }
        }

        $sheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    }

    /**
     * Listas desplegables con estilo "aviso": se puede escribir otro valor (la fila
     * irá a revisión), pero Excel advierte antes. Nunca se bloquea la carga.
     *
     * @param  array<int, TemplateColumn>  $columns
     */
    private function validations(Worksheet $sheet, array $columns): void
    {
        $spreadsheet = $sheet->getParent();

        foreach ($columns as $i => $column) {
            if (! $column->list || ! $spreadsheet->getNamedRange("lista_{$column->list}")) {
                continue; // p. ej. un país sin ciudades sembradas: texto libre
            }

            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $validation = (new DataValidation)
                ->setType(DataValidation::TYPE_LIST)
                ->setErrorStyle(DataValidation::STYLE_WARNING)
                ->setAllowBlank(true)
                ->setShowDropDown(true)
                ->setShowErrorMessage(true)
                ->setErrorTitle('Valor fuera de la lista')
                ->setError('Puedes dejarlo: la fila irá a revisión antes de crear la ficha. Si es una errata, corrígelo.')
                // Sin "=": en el XML de Excel la fórmula de validación va sin él; con él,
                // Excel "repara" el archivo al abrirlo y descarta la lista.
                ->setFormula1("lista_{$column->list}");

            $sheet->setDataValidation("{$letter}2:{$letter}".(self::ROWS + 1), $validation);
        }
    }

    /**
     * @param  array<int, TemplateColumn>  $columns
     */
    private function instructions(Worksheet $sheet, Country $country, array $columns): void
    {
        $required = collect($columns)->filter->required->map->label->implode(', ');

        $lines = [
            ["Plantilla de carga de médicos · {$country->name}"],
            [''],
            ['Cómo llenarla'],
            ['1. Trabaja en la hoja "Médicos". Una fila por CONSULTORIO: un médico con dos consultorios ocupa dos filas.'],
            ['2. Las filas del mismo médico comparten el mismo "ID del médico". Llena sus datos (nombres, especialidades, biografía…) al menos en la primera; en las demás puedes repetirlos o dejarlos vacíos.'],
            ['3. Si repites un dato del médico con un valor distinto (por ejemplo, otro apellido), esa fila irá a revisión: nunca se elige un valor en silencio.'],
            ["4. Columnas obligatorias (marcadas con *): {$required}."],
            ['5. Las columnas con flecha tienen lista. Puedes escribir otro valor: Excel te avisará y la fila se revisará a mano.'],
            ['6. Pasa el ratón sobre cada encabezado para ver su nota de ayuda. Mira la hoja "Ejemplo".'],
            [''],
            ['Qué pasa al cargarla'],
            ['• Las fichas se crean en BORRADOR. Se publican después, solo si tienen especialidad, consultorio con ciudad y un contacto.'],
            ['• El "ID del médico" y el N.º de colegiado evitan duplicados: si ya existe una ficha con ellos, se actualiza en lugar de crear otra.'],
            ['• Un nombre parecido a una ficha existente NO se fusiona automáticamente: va a revisión humana.'],
            ['• Las personas que pidieron no aparecer en el directorio no se vuelven a crear aunque estén en el archivo.'],
            ['• Los teléfonos se guardan en formato internacional; escríbelos como los darías (2222-3333).'],
            [''],
            ['No cambies los encabezados ni el orden de las columnas, ni borres las hojas ocultas: el importador los usa para reconocer el archivo.'],
            ['Plantilla versión '.TemplateColumns::VERSION.' · generada el '.now()->format('d/m/Y')],
        ];

        $sheet->fromArray($lines);
        $sheet->getColumnDimension('A')->setWidth(120);
        $sheet->getStyle('A1:A'.count($lines))->getAlignment()->setWrapText(true);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB(self::PETROL);
        foreach (['A3', 'A11'] as $cell) {
            $sheet->getStyle($cell)->getFont()->setBold(true)->getColor()->setRGB(self::PETROL);
        }
        $sheet->getStyle('A18')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::PETROL_SOFT);
    }

    /**
     * @param  array<int, TemplateColumn>  $columns
     */
    private function example(Worksheet $sheet, Country $country, array $columns): void
    {
        $region = TemplateColumns::regionLabel($country);
        $rows = [
            ['doctor_ref' => 'MED-001', 'first_name' => 'Ana', 'last_name' => 'Rojas Méndez', 'gender' => 'Femenino', 'license_number' => 'MED-4821',
                'specialty_1' => 'Cardiología', 'languages' => 'Español; Inglés', 'email' => 'consultas@ejemplo.com',
                'headline' => 'Cardióloga clínica', 'location_name' => 'Torre Médica, piso 4', 'address' => 'Avenida Central 123',
                'city' => 'Ciudad Ejemplo', 'region' => "{$region} Ejemplo", 'location_type' => 'Consultorio', 'phone' => '2222-3333'],
            ['doctor_ref' => 'MED-001', 'address' => 'Calle 5, local 12', 'city' => 'Otra Ciudad', 'region' => "{$region} Ejemplo",
                'location_type' => 'Clínica', 'phone' => '2222-4444'],
            ['doctor_ref' => 'MED-002', 'first_name' => 'Luis', 'last_name' => 'Soto Vargas', 'gender' => 'Masculino',
                'specialty_1' => 'Pediatría', 'specialty_2' => 'Medicina General', 'address' => 'Barrio Norte, casa 8',
                'city' => 'Ciudad Ejemplo', 'location_type' => 'Consultorio', 'whatsapp' => '8888-1234'],
        ];

        foreach ($rows as $r => $row) {
            foreach ($columns as $i => $column) {
                if (isset($row[$column->key])) {
                    $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($i + 1).($r + 2), $row[$column->key], 'str');
                }
            }
        }
    }
}
