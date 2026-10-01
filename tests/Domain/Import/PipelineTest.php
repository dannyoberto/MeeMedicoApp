<?php

use App\Domain\Directory\Actions\CreateSuppressionAction;
use App\Domain\Directory\Actions\DoctorLocationsAction;
use App\Domain\Directory\Enums\LocationType;
use App\Domain\Import\Actions\ApplyImportBatchAction;
use App\Domain\Import\Actions\IngestImportFileAction;
use App\Domain\Import\Actions\MapImportValueAction;
use App\Domain\Import\Actions\MatchImportBatchAction;
use App\Domain\Import\Actions\NormalizeImportBatchAction;
use App\Domain\Import\Actions\PublishImportBatchAction;
use App\Domain\Import\Actions\ResolveImportRowAction;
use App\Domain\Import\Enums\ImportResolution;
use App\Domain\Import\Exceptions\ImportFileException;
use App\Domain\Import\Template\SampleWriter;
use App\Domain\Import\Template\TemplateColumns;
use App\Domain\Import\Template\TemplateWriter;
use App\Models\Doctor;
use App\Models\ImportBatch;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/*
| El pipeline completo contra archivos reales generados con la plantilla.
| AGENTS.md §4: nunca escribe directo en doctors, nunca crea Users, nunca fusiona por
| nombre, consulta supresiones antes que nada, apply nunca publica.
*/

/**
 * @param  array<int, array<string, string>>  $rows
 */
function importFile(array $rows, string $country = 'CR'): string
{
    $path = storage_path('framework/testing/import-'.uniqid().'.xlsx');
    $countryModel = country($country);
    app(TemplateWriter::class)->write($countryModel, $path);

    $book = IOFactory::load($path);
    $sheet = $book->getSheetByName(TemplateWriter::SHEET_DATA);
    $columns = TemplateColumns::all($countryModel);
    foreach ($rows as $r => $row) {
        foreach ($columns as $i => $column) {
            if (isset($row[$column->key])) {
                $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($i + 1).($r + 2), $row[$column->key], 'str');
            }
        }
    }
    (new Xlsx($book))->save($path);
    $book->disconnectWorksheets();

    return $path;
}

function row(array $overrides = []): array
{
    return [
        'doctor_ref' => 'MED-001', 'first_name' => 'Ana', 'last_name' => 'Rojas Méndez', 'license_number' => 'LIC-1',
        'specialty_1' => 'Cardiología', 'address' => 'Avenida Central 123', 'city' => 'Escazú', 'phone' => '2222-3333',
        ...$overrides,
    ];
}

/**
 * Leer + normalizar + buscar coincidencias, como la cadena automática.
 */
function runToReview(string $path, string $country = 'CR', ?string $source = null): ImportBatch
{
    $batch = app(IngestImportFileAction::class)->execute($path, basename($path), country($country), $source ?? 'plantilla_meemedico', null);
    app(NormalizeImportBatchAction::class)->execute($batch);

    return app(MatchImportBatchAction::class)->execute($batch->fresh());
}

function rowsOf(ImportBatch $batch, string $ref)
{
    return $batch->rows()->whereRaw("raw_payload->>'doctor_ref' = ?", [$ref])->orderBy('row_number')->get();
}

beforeEach(function () {
    Storage::fake('local');
    $this->city = city('CR', 'Escazú');
    $this->heredia = city('CR', 'Heredia');
});

afterEach(fn () => array_map(fn ($f) => @unlink($f), glob(storage_path('framework/testing/import-*.xlsx'))));

describe('leer', function () {
    it('rechaza una plantilla de otro país', function () {
        expect(fn () => runToReview(importFile([row()], 'GT'), 'CR'))->toThrow(ImportFileException::class, 'GT');
    });

    it('rechaza el mismo archivo dos veces', function () {
        $path = importFile([row()]);
        runToReview($path);

        expect(fn () => runToReview($path))->toThrow(ImportFileException::class, 'ya se cargó');
    });

    it('rechaza un archivo con encabezados alterados', function () {
        $path = importFile([row()]);
        $book = IOFactory::load($path);
        $book->getSheetByName(TemplateWriter::SHEET_DATA)->setCellValue('B1', 'Nombre de pila');
        (new Xlsx($book))->save($path);

        expect(fn () => runToReview($path))->toThrow(ImportFileException::class, 'encabezados');
    });

    it('ignora las filas vacías y guarda el archivo en el disco privado', function () {
        $batch = runToReview(importFile([row(), row(['doctor_ref' => 'MED-002', 'first_name' => 'Luis', 'license_number' => 'LIC-2'])]));

        expect($batch->rows_total)->toBe(2);
        Storage::disk('local')->assertExists($batch->file_path);
    });
});

describe('aplicar', function () {
    it('un médico con dos consultorios da una ficha completa en borrador, sin crear usuarios', function () {
        $users = User::count();
        $batch = runToReview(importFile([
            row(['email' => 'Ana@Ejemplo.com', 'languages' => 'Español; Inglés', 'specialty_2' => 'PEDIATRIA', 'headline' => 'Cardióloga']),
            row(['first_name' => '', 'last_name' => '', 'address' => 'Plaza Heredia 4', 'city' => 'Heredia', 'phone' => '', 'whatsapp' => '8888-1234']),
        ]));

        $report = app(ApplyImportBatchAction::class)->execute($batch, null);
        $doctor = Doctor::where('license_number', 'LIC-1')->firstOrFail();

        expect($report['created'])->toBe(1)
            ->and($doctor->status->value)->toBe('draft')
            ->and($doctor->source->value)->toBe('import')
            ->and($doctor->import_batch_id)->toBe($batch->id)
            ->and($doctor->locations()->count())->toBe(2)
            ->and($doctor->specialties()->pluck('slug')->sort()->values()->all())->toBe(['cardiologia', 'pediatria'])
            ->and($doctor->contacts()->pluck('value_normalized')->sort()->values()->all())->toBe(['+50622223333', '+50688881234', 'ana@ejemplo.com'])
            ->and($doctor->languages()->count())->toBe(2)
            ->and($doctor->profile->headline)->toBe('Cardióloga')
            ->and($doctor->externalReferences()->where('source', 'plantilla_meemedico')->value('reference'))->toBe('MED-001')
            ->and(User::count())->toBe($users)
            ->and($batch->fresh()->status->value)->toBe('completed');
    });

    it('nunca publica y es idempotente', function () {
        $batch = runToReview(importFile([row()]));
        app(ApplyImportBatchAction::class)->execute($batch, null);
        app(ApplyImportBatchAction::class)->execute($batch->fresh(), null);

        expect(Doctor::where('license_number', 'LIC-1')->count())->toBe(1)
            ->and(Doctor::where('status', 'active')->count())->toBe(0);
    });

    it('reutiliza la dirección compartida de una torre médica', function () {
        $batch = runToReview(importFile([
            row(),
            row(['doctor_ref' => 'MED-002', 'first_name' => 'Luis', 'last_name' => 'Soto', 'license_number' => 'LIC-2', 'address' => 'Avenida  central 123']),
        ]));
        app(ApplyImportBatchAction::class)->execute($batch, null);

        expect(Location::count())->toBe(1)
            ->and(Location::first()->doctors()->count())->toBe(2);
    });

    it('recargar con los mismos IDs completa la ficha sin sobrescribir y anota el conflicto', function () {
        app(ApplyImportBatchAction::class)->execute(runToReview(importFile([row()])), null);
        $doctor = Doctor::where('license_number', 'LIC-1')->firstOrFail();

        $second = runToReview(importFile([
            row(['last_name' => 'Rojas Mora', 'headline' => 'Cardióloga clínica']),
            row(['first_name' => '', 'last_name' => '', 'address' => 'Plaza Heredia 4', 'city' => 'Heredia']),
        ]));
        expect(rowsOf($second, 'MED-001')->first()->status->value)->toBe('matched')
            ->and(rowsOf($second, 'MED-001')->first()->match_type->value)->toBe('external_ref');

        app(ApplyImportBatchAction::class)->execute($second, null);
        $doctor->refresh();
        $issues = collect(rowsOf($second, 'MED-001')->first()->validation_errors);

        expect($doctor->last_name)->toBe('Rojas Méndez')
            ->and($doctor->profile->headline)->toBe('Cardióloga clínica')
            ->and($doctor->locations()->count())->toBe(2)
            ->and($issues->where('code', 'existing_value')->where('field', 'last_name'))->toHaveCount(1)
            ->and(Doctor::count())->toBe(1);
    });
});

describe('coincidencias', function () {
    it('la licencia del país es una coincidencia fuerte', function () {
        app(ApplyImportBatchAction::class)->execute(runToReview(importFile([row()])), null);
        $batch = runToReview(importFile([row(['doctor_ref' => 'OTRO-ID'])]), source: 'colegio_cr');

        expect(rowsOf($batch, 'OTRO-ID')->first())->status->value->toBe('matched')->match_type->value->toBe('license');
    });

    it('el mismo teléfono o un nombre similar en la misma ciudad van a revisión con candidatos', function (array $overrides, string $type) {
        app(ApplyImportBatchAction::class)->execute(runToReview(importFile([row()])), null);
        $batch = runToReview(importFile([row(['doctor_ref' => 'NUEVO', 'license_number' => '', ...$overrides])]), source: 'otra');
        $first = rowsOf($batch, 'NUEVO')->first();

        expect($first->status->value)->toBe('needs_review')
            ->and($first->match_type->value)->toBe($type)
            ->and($first->candidate_doctor_ids)->toHaveCount(1);
    })->with([
        'teléfono' => [['first_name' => 'Otra', 'last_name' => 'Persona'], 'phone'],
        'nombre en la misma ciudad' => [['first_name' => 'Ana', 'last_name' => 'Rojas Mendes', 'phone' => '2222-9999'], 'name_city'],
    ]);

    it('nunca fusiona por nombre: homónimos con distinta licencia son fichas distintas', function () {
        $batch = runToReview(importFile([
            row(),
            row(['doctor_ref' => 'MED-002', 'license_number' => 'LIC-2', 'city' => 'Heredia', 'address' => 'Calle 2', 'phone' => '2222-4444']),
        ]));
        app(ApplyImportBatchAction::class)->execute($batch, null);

        expect(Doctor::where('last_name', 'Rojas Méndez')->count())->toBe(2);
    });

    it('la misma licencia en dos médicos del archivo manda ambos a revisión', function () {
        $batch = runToReview(importFile([row(), row(['doctor_ref' => 'MED-002', 'first_name' => 'Pablo', 'phone' => '2222-5555'])]));

        expect(rowsOf($batch, 'MED-001')->first()->status->value)->toBe('needs_review')
            ->and(rowsOf($batch, 'MED-002')->first()->status->value)->toBe('needs_review');
    });

    it('una supresión fuerte deja las filas suprimidas y nunca crea la ficha', function () {
        app(CreateSuppressionAction::class)->execute(country(), 'LIC-1', null, null, null, 'Pidió salir', now(), null);
        $batch = runToReview(importFile([row()]));
        app(ApplyImportBatchAction::class)->execute($batch, null);

        expect(rowsOf($batch, 'MED-001')->first()->status->value)->toBe('suppressed')
            ->and(Doctor::count())->toBe(0);
    });
});

describe('revisión y mapeo', function () {
    it('una ciudad desconocida va a revisión; asignarla graba el alias y la fila queda lista', function () {
        $batch = runToReview(importFile([row(['city' => 'Escazu Centro'])]));
        expect(rowsOf($batch, 'MED-001')->first()->status->value)->toBe('needs_review');

        app(MapImportValueAction::class)->city($batch, 'Escazu Centro', $this->city, admin());
        app(NormalizeImportBatchAction::class)->execute($batch);
        app(MatchImportBatchAction::class)->execute($batch->fresh());

        expect(rowsOf($batch, 'MED-001')->first()->status->value)->toBe('new')
            ->and($this->city->aliases()->where('alias_normalized', 'escazu centro')->exists())->toBeTrue();
    });

    it('una especialidad desconocida se asigna y el alias sirve para la próxima carga', function () {
        $batch = runToReview(importFile([row(['specialty_1' => 'Cardiología Clínica'])]));
        app(MapImportValueAction::class)->specialty($batch, 'Cardiología Clínica', specialty(), admin());
        app(NormalizeImportBatchAction::class)->execute($batch);

        expect(rowsOf($batch, 'MED-001')->first()->n_specialty_ids)->toBe([specialty()->id]);
    });

    it('un conflicto entre filas del mismo médico va a revisión; aprobar usa la primera fila', function () {
        $batch = runToReview(importFile([row(), row(['last_name' => 'Rojas Mora', 'address' => 'Calle 2'])]));
        $first = rowsOf($batch, 'MED-001')->first();
        expect($first->status->value)->toBe('needs_review');

        app(ResolveImportRowAction::class)->execute($first, ImportResolution::CreateNew, admin());
        app(ApplyImportBatchAction::class)->execute($batch->fresh(), null);

        expect(Doctor::firstOrFail()->last_name)->toBe('Rojas Méndez')
            ->and(Doctor::firstOrFail()->locations()->count())->toBe(2);
    });

    it('no se puede aprobar mientras falten datos: hay que corregirlos', function () {
        $batch = runToReview(importFile([row(['city' => 'Inexistente'])]));

        expect(fn () => app(ResolveImportRowAction::class)->execute(rowsOf($batch, 'MED-001')->first(), ImportResolution::CreateNew, admin()))
            ->toThrow(ImportFileException::class);
    });

    it('vincular a una ficha existente la completa en lugar de crear otra', function () {
        $existing = makeDoctor(['first_name' => 'Ana', 'last_name' => 'Rojas Méndez']);
        app(DoctorLocationsAction::class)->attachNew($existing, ['city_id' => $this->city->id, 'address' => 'Otra calle'], LocationType::Office, null);

        $batch = runToReview(importFile([row(['license_number' => ''])]));
        $first = rowsOf($batch, 'MED-001')->first();
        expect($first->status->value)->toBe('needs_review');

        app(ResolveImportRowAction::class)->execute($first, ImportResolution::LinkExisting, admin(), $existing);
        app(ApplyImportBatchAction::class)->execute($batch->fresh(), null);

        expect(Doctor::count())->toBe(1)->and($existing->locations()->count())->toBe(2);
    });

    it('descartar deja las filas omitidas', function () {
        $batch = runToReview(importFile([row(['city' => 'Inexistente'])]));
        app(ResolveImportRowAction::class)->execute(rowsOf($batch, 'MED-001')->first(), ImportResolution::Discard, admin());
        app(ApplyImportBatchAction::class)->execute($batch->fresh(), null);

        expect(rowsOf($batch, 'MED-001')->first()->status->value)->toBe('skipped')->and(Doctor::count())->toBe(0);
    });
});

it('publicar solo publica las fichas completas e informa de las demás', function () {
    $batch = runToReview(importFile([
        row(),
        row(['doctor_ref' => 'MED-002', 'first_name' => 'Luis', 'last_name' => 'Soto', 'license_number' => 'LIC-2', 'address' => 'Calle 9', 'phone' => '']),
    ]));
    app(ApplyImportBatchAction::class)->execute($batch, null);

    $report = app(PublishImportBatchAction::class)->execute($batch->fresh(), null);

    expect($report['published'])->toBe(1)
        ->and($report['skipped'])->toBe(1)
        ->and(array_keys($report['reasons']))->toContain('Un contacto público')
        ->and($batch->fresh()->notes)->toContain('1 publicadas');
});

it('la muestra sintética se lee entera y deja los casos difíciles en revisión', function () {
    $path = storage_path('framework/testing/import-muestra.xlsx');
    app(SampleWriter::class)->write(country(), $path);
    $batch = runToReview($path);

    expect($batch->rows_total)->toBeGreaterThan(30)
        ->and($batch->rows_review)->toBeGreaterThan(0);
});
