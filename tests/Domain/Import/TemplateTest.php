<?php

use App\Domain\Import\Template\TemplateColumn;
use App\Domain\Import\Template\TemplateColumns;
use App\Domain\Import\Template\TemplateWriter;
use App\Models\Specialty;
use OpenSpout\Reader\XLSX\Reader;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

/*
| La plantilla es el contrato de entrada del importador. Se relee con OpenSpout,
| el mismo lector que usará import:ingest: si el contrato y el archivo se
| desalinean, este test lo detecta antes que un lote real.
*/

/**
 * @return array<string, array<int, array<int, mixed>>> hoja => filas
 */
function readTemplate(string $path): array
{
    $reader = new Reader;
    $reader->open($path);
    $sheets = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $sheets[$sheet->getName()][] = $row->toArray();
        }
        $sheets[$sheet->getName()] ??= [];
    }
    $reader->close();

    return $sheets;
}

beforeEach(function () {
    $this->city = city('CR', 'Escazú');
    $this->path = storage_path('framework/testing/plantilla-cr.xlsx');
    app(TemplateWriter::class)->write(country('CR'), $this->path);
});

afterEach(fn () => @unlink($this->path));

it('tiene las hojas de trabajo, ayuda, ejemplo, listas y metadatos', function () {
    expect(array_keys(readTemplate($this->path)))
        ->toBe([TemplateWriter::SHEET_DATA, TemplateWriter::SHEET_LISTS, TemplateWriter::SHEET_HELP, TemplateWriter::SHEET_EXAMPLE, TemplateWriter::SHEET_META]);
});

it('los encabezados coinciden exactamente con el contrato', function () {
    $headers = readTemplate($this->path)[TemplateWriter::SHEET_DATA][0];

    expect($headers)->toBe(array_map(fn (TemplateColumn $c) => $c->header(), TemplateColumns::all(country('CR'))));
});

it('cada encabezado se reconoce de vuelta como su clave, aunque cambien mayúsculas o espacios', function () {
    foreach (TemplateColumns::all(country('CR')) as $column) {
        expect(TemplateColumns::keyForHeader(country('CR'), '  '.mb_strtoupper($column->header()).' '))->toBe($column->key);
    }
});

it('usa la división administrativa de cada país', function () {
    expect(TemplateColumns::regionLabel(country('GT')))->toBe('Departamento')
        ->and(TemplateColumns::regionLabel(country('VE')))->toBe('Estado')
        ->and(TemplateColumns::regionLabel(country('CR')))->toBe('Provincia');
});

it('declara versión y país en _meta para que el importador rechace un archivo ajeno', function () {
    $meta = collect(readTemplate($this->path)[TemplateWriter::SHEET_META])->mapWithKeys(fn ($r) => [$r[0] => $r[1]]);

    expect($meta['country'])->toBe('CR')
        ->and((int) $meta['template_version'])->toBe(TemplateColumns::VERSION);
});

it('las listas salen del catálogo vigente', function () {
    $lists = readTemplate($this->path)[TemplateWriter::SHEET_LISTS];
    $specialties = array_values(array_filter(array_column(array_slice($lists, 1), 0)));

    expect($specialties)->toBe(Specialty::where('status', 'active')->orderBy('name')->pluck('name')->all())
        ->and(array_filter(array_column(array_slice($lists, 1), 3)))->toContain('Escazú');
});

/**
 * Carga la hoja de datos con PhpSpreadsheet (para inspeccionar validaciones y formatos,
 * que OpenSpout no expone) y libera el libro: cada uno ocupa decenas de MB.
 */
function dataSheet(string $path, Closure $inspect): void
{
    $book = IOFactory::load($path);
    try {
        $inspect($book->getSheetByName(TemplateWriter::SHEET_DATA));
    } finally {
        $book->disconnectWorksheets();
        unset($book);
        gc_collect_cycles();
    }
}

it('aplica listas desplegables en modo aviso y formato texto donde importa', function () {
    dataSheet($this->path, function ($sheet) {
        $columns = TemplateColumns::all(country('CR'));
        $letter = fn (string $key) => Coordinate::stringFromColumnIndex(
            collect($columns)->search(fn ($c) => $c->key === $key) + 1,
        );

        $validation = $sheet->getCell($letter('specialty_1').'100')->getDataValidation();
        expect($validation->getType())->toBe('list')
            ->and($validation->getErrorStyle())->toBe('warning')
            ->and($validation->getFormula1())->toBe('lista_especialidades')
            ->and($sheet->getCell($letter('city').'100')->getDataValidation()->getFormula1())->toBe('lista_ciudades');

        foreach (['license_number', 'phone', 'whatsapp', 'doctor_ref'] as $key) {
            expect($sheet->getStyle($letter($key).'500')->getNumberFormat()->getFormatCode())->toBe('@');
        }
    });
});

it('un país sin ciudades deja la ciudad como texto libre', function () {
    $path = storage_path('framework/testing/plantilla-ve.xlsx');
    app(TemplateWriter::class)->write(country('VE'), $path);
    $cityColumn = collect(TemplateColumns::all(country('VE')))->search(fn ($c) => $c->key === 'city') + 1;

    dataSheet($path, fn ($sheet) => expect(
        $sheet->getCell(Coordinate::stringFromColumnIndex($cityColumn).'10')->hasDataValidation(),
    )->toBeFalse());

    @unlink($path);
});

it('el comando genera la plantilla por código ISO y rechaza un país desconocido', function () {
    $path = storage_path('framework/testing/cmd.xlsx');

    $this->artisan('import:template', ['country' => 'gt', '--output' => $path])->assertSuccessful();
    expect(file_exists($path))->toBeTrue();
    @unlink($path);

    $this->artisan('import:template', ['country' => 'XX'])->assertFailed();
});
