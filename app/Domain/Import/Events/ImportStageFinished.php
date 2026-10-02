<?php

namespace App\Domain\Import\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Terminó una etapa del pipeline que la persona espera: buscar coincidencias (el lote
 * queda listo para revisar), aplicar o publicar. El dominio solo lo anuncia; el aviso
 * en el backoffice lo envía app/Listeners/NotifyImportStage.
 */
final class ImportStageFinished
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>|null  $report  informe de la etapa, si lo tiene (aplicar, publicar)
     */
    public function __construct(
        public readonly string $batchId,
        public readonly string $stage,
        public readonly ?string $actorId,
        public readonly ?array $report = null,
    ) {}
}
