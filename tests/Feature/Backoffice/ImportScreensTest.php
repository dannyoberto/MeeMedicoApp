<?php

use App\Domain\Import\Template\TemplateColumns;
use App\Domain\Import\Template\TemplateWriter;
use App\Filament\Resources\ImportBatches\Pages\CreateImportBatch;
use App\Filament\Resources\ImportBatches\Pages\ViewImportBatch;
use App\Filament\Resources\ImportBatches\RelationManagers\RowsRelationManager;
use App\Models\Doctor;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/*
| El flujo del operador en el backoffice: subir → (cola: normalizar y buscar) → revisar →
| aplicar → publicar. En tests la cola es síncrona: cada paso termina antes de seguir.
*/

function templateUpload(array $rows): UploadedFile
{
    $path = storage_path('framework/testing/ui-'.uniqid().'.xlsx');
    app(TemplateWriter::class)->write(country(), $path);
    $book = IOFactory::load($path);
    $sheet = $book->getSheetByName(TemplateWriter::SHEET_DATA);
    $columns = TemplateColumns::all(country());
    foreach ($rows as $r => $row) {
        foreach ($columns as $i => $column) {
            if (isset($row[$column->key])) {
                $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($i + 1).($r + 2), $row[$column->key], 'str');
            }
        }
    }
    (new Xlsx($book))->save($path);
    $book->disconnectWorksheets();

    return UploadedFile::fake()->createWithContent('lote-prueba.xlsx', file_get_contents($path));
}

beforeEach(function () {
    Storage::fake('local');
    $this->actingAs($this->admin = admin());
    $this->city = city('CR', 'Escazú');
});

afterEach(fn () => array_map(fn ($f) => @unlink($f), glob(storage_path('framework/testing/ui-*.xlsx'))));

it('el operador sube, revisa, asigna una ciudad, aplica y publica', function () {
    $base = ['license_number' => 'LIC-1', 'specialty_1' => 'Cardiología', 'address' => 'Avenida 1', 'phone' => '2222-3333'];

    Livewire::test(CreateImportBatch::class)
        ->set('data.country_id', country()->id)
        ->set('data.source', 'plantilla_meemedico')
        ->set('data.file', templateUpload([
            ['doctor_ref' => 'MED-1', 'first_name' => 'Ana', 'last_name' => 'Rojas', 'city' => 'Escazú', ...$base],
            ['doctor_ref' => 'MED-2', 'first_name' => 'Luis', 'last_name' => 'Soto', 'city' => 'Escazu Centro', ...$base, 'license_number' => 'LIC-2', 'phone' => '2222-4444'],
        ]))
        ->call('create')
        ->assertHasNoErrors();

    $batch = ImportBatch::firstOrFail();
    expect($batch->status->value)->toBe('review')
        ->and($batch->rows_total)->toBe(2)
        ->and($batch->rows_review)->toBe(1)
        ->and($batch->created_by_user_id)->toBe($this->admin->id);

    $pending = $batch->rows()->where('status', 'needs_review')->firstOrFail();
    Livewire::test(RowsRelationManager::class, ['ownerRecord' => $batch, 'pageClass' => ViewImportBatch::class])
        ->mountTableAction('assignCity', $pending->id)
        ->set('mountedActions.0.data.city_id', $this->city->id)
        ->callMountedTableAction();

    expect($batch->fresh()->rows_review)->toBe(0);

    Livewire::test(ViewImportBatch::class, ['record' => $batch->id])->mountAction('apply')->callMountedAction();
    expect(Doctor::count())->toBe(2)
        ->and(Doctor::where('status', 'draft')->count())->toBe(2)
        ->and($batch->fresh()->status->value)->toBe('completed');

    Livewire::test(ViewImportBatch::class, ['record' => $batch->id])->mountAction('publish')->callMountedAction();
    expect(Doctor::where('status', 'active')->count())->toBe(2)
        ->and($batch->fresh()->notes)->toContain('2 publicadas');
});

it('un archivo de otro país se rechaza con un mensaje y no crea lote', function () {
    Livewire::test(CreateImportBatch::class)
        ->set('data.country_id', country('GT')->id)
        ->set('data.source', 'plantilla_meemedico')
        ->set('data.file', templateUpload([['doctor_ref' => 'X', 'first_name' => 'A', 'last_name' => 'B']]))
        ->call('create');

    expect(ImportBatch::count())->toBe(0);
});

it('descartar desde la cola de revisión', function () {
    Livewire::test(CreateImportBatch::class)
        ->set('data.country_id', country()->id)->set('data.source', 'x')
        ->set('data.file', templateUpload([['doctor_ref' => 'MED-1', 'first_name' => 'Ana', 'last_name' => 'Rojas', 'specialty_1' => 'Cardiología', 'address' => 'A', 'city' => 'Ninguna']]))
        ->call('create');
    $batch = ImportBatch::firstOrFail();
    $row = $batch->rows()->firstOrFail();

    Livewire::test(RowsRelationManager::class, ['ownerRecord' => $batch, 'pageClass' => ViewImportBatch::class])
        ->mountTableAction('discard', $row->id)->callMountedTableAction();

    expect($row->fresh()->status->value)->toBe('skipped');
});

/**
 * Dos médicos en revisión por ciudad desconocida: descartarlos es la decisión posible.
 *
 * @return array{0: ImportBatch, 1: ImportRow, 2: ImportRow}
 */
function twoRowsInReview(): array
{
    Livewire::test(CreateImportBatch::class)
        ->set('data.country_id', country()->id)->set('data.source', 'x')
        ->set('data.file', templateUpload([
            ['doctor_ref' => 'MED-1', 'first_name' => 'Ana', 'last_name' => 'Rojas', 'specialty_1' => 'Cardiología', 'address' => 'A', 'city' => 'Ninguna'],
            ['doctor_ref' => 'MED-2', 'first_name' => 'Luis', 'last_name' => 'Soto', 'specialty_1' => 'Cardiología', 'address' => 'B', 'city' => 'Otra'],
        ]))
        ->call('create');

    $batch = ImportBatch::firstOrFail();
    [$first, $second] = $batch->rows()->orderBy('row_number')->get()->all();

    return [$batch, $first, $second];
}

it('al subir un lote, quien lo sube recibe el aviso cuando está listo para revisar', function () {
    twoRowsInReview();

    expect($this->admin->notifications()->firstOrFail()->data['title'])->toBe('Lote listo para revisar');
});

it('revisar en el panel lateral: al decidir pasa sola a la siguiente fila y se cierra con la última', function () {
    [$batch, $first, $second] = twoRowsInReview();

    $rows = Livewire::test(RowsRelationManager::class, ['ownerRecord' => $batch, 'pageClass' => ViewImportBatch::class])
        ->mountTableAction('details', $first->id)
        ->assertSeeText('Ciudad no reconocida')
        ->call('mountAction', 'discard')
        ->callMountedAction();

    expect($first->fresh()->status->value)->toBe('skipped')
        ->and($rows->instance()->mountedActions)->toHaveCount(1)
        ->and($rows->instance()->mountedActions[0]['context']['recordKey'])->toBe($second->id);

    $rows->call('mountAction', 'discard')->callMountedAction();

    expect($second->fresh()->status->value)->toBe('skipped')
        ->and($rows->instance()->mountedActions)->toBeEmpty();
});

it('descartar en lote desde la cola de revisión', function () {
    [$batch, $first, $second] = twoRowsInReview();

    Livewire::test(RowsRelationManager::class, ['ownerRecord' => $batch, 'pageClass' => ViewImportBatch::class])
        ->callTableBulkAction('discardSelected', [$first, $second]);

    expect($first->fresh()->status->value)->toBe('skipped')
        ->and($second->fresh()->status->value)->toBe('skipped')
        ->and($batch->fresh()->rows_review)->toBe(0);
});
