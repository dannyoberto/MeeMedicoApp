<?php

namespace App\Domain\Import;

use App\Domain\Import\Jobs\RunImportStage;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

/**
 * Encadena las etapas en cola. Tras leer el archivo, normalizar y buscar coincidencias
 * corren solas y el lote queda "En revisión" (decisión de la Etapa 6). Aplicar y
 * publicar se disparan aparte, por decisión humana.
 */
class ImportPipeline
{
    public function process(ImportBatch $batch): void
    {
        Bus::chain([
            new RunImportStage($batch->getKey(), RunImportStage::NORMALIZE),
            new RunImportStage($batch->getKey(), RunImportStage::MATCH),
        ])->dispatch();
    }

    public function apply(ImportBatch $batch, ?User $actor): void
    {
        RunImportStage::dispatch($batch->getKey(), RunImportStage::APPLY, $actor?->getKey());
    }

    public function publish(ImportBatch $batch, ?User $actor): void
    {
        RunImportStage::dispatch($batch->getKey(), RunImportStage::PUBLISH, $actor?->getKey());
    }
}
