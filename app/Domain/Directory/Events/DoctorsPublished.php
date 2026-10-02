<?php

namespace App\Domain\Directory\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Terminó una publicación en lote en segundo plano. El dominio solo lo anuncia;
 * cómo se avisa a la persona (notificación del backoffice) es cosa de app/Listeners.
 */
final class DoctorsPublished
{
    use Dispatchable;

    /**
     * @param  array{published: int, already: int, skipped: int, reasons: array<string, int>}  $report
     */
    public function __construct(
        public readonly array $report,
        public readonly ?string $actorId,
    ) {}
}
