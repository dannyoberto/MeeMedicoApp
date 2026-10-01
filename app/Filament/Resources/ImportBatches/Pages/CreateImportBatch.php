<?php

namespace App\Filament\Resources\ImportBatches\Pages;

use App\Domain\Import\Actions\IngestImportFileAction;
use App\Domain\Import\Exceptions\ImportFileException;
use App\Domain\Import\ImportPipeline;
use App\Filament\Resources\ImportBatches\ImportBatchResource;
use App\Filament\Support\DomainAction;
use App\Models\Country;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CreateImportBatch extends CreateRecord
{
    protected static string $resource = ImportBatchResource::class;

    protected static ?string $title = 'Subir lote';

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        $disk = Storage::disk('local');
        $upload = $data['file'];

        try {
            $batch = app(IngestImportFileAction::class)->execute(
                $disk->path($upload),
                $data['file_name'] ?? basename($upload),
                Country::findOrFail($data['country_id']),
                $data['source'],
                auth()->user(),
            );
        } catch (ImportFileException $e) {
            DomainAction::notify('No se pudo cargar el archivo', [$e->getMessage()]);

            throw new Halt;
        } finally {
            // La subida temporal sobra: IngestImportFileAction guarda su propia copia.
            $disk->delete($upload);
        }

        // Normalizar y buscar coincidencias corren solas, en cola.
        app(ImportPipeline::class)->process($batch);

        return $batch;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Lote cargado: se están normalizando las filas y buscando coincidencias.';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
