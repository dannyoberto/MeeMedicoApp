<?php

namespace App\Domain\Import\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Falló una etapa del pipeline: el lote quedó en "failed" con el motivo en sus notas.
 */
final class ImportStageFailed
{
    use Dispatchable;

    public function __construct(
        public readonly string $batchId,
        public readonly string $stage,
        public readonly ?string $actorId,
        public readonly string $message,
    ) {}
}
