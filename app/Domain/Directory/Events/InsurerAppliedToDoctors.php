<?php

namespace App\Domain\Directory\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Terminó en segundo plano una asignación (o retirada) de aseguradora a muchos médicos.
 * El dominio solo lo anuncia; el aviso a la persona es cosa de app/Listeners.
 */
final class InsurerAppliedToDoctors
{
    use Dispatchable;

    /**
     * @param  array{changed: int, unchanged: int, skipped: int, reasons: array<string, int>}  $report
     */
    public function __construct(
        public readonly string $insurerName,
        public readonly bool $attach,
        public readonly array $report,
        public readonly ?string $actorId,
    ) {}
}
