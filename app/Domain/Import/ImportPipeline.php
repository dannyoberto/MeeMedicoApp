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
    /**
     * @param  User|null  $actor  quien lo lanzó: recibe el aviso al terminar (si no, el creador del lote)
     */
    public function process(ImportBatch $batch, ?User $actor = null): void
    {
        Bus::chain([
            new RunImportStage($batch->getKey(), RunImportStage::NORMALIZE, $actor?->getKey()),
            new RunImportStage($batch->getKey(), RunImportStage::MATCH, $actor?->getKey()),
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
