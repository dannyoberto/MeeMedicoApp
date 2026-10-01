<?php

namespace App\Domain\Import\Commands;

use App\Models\ImportBatch;
use Illuminate\Console\Command;

/**
 * Tabla de contadores de un lote para la salida de los comandos.
 */
final class BatchSummary
{
    public static function print(Command $command, ImportBatch $batch): void
    {
        $command->table(
            ['Estado', 'Total', 'Nuevas', 'Coincidencias', 'En revisión', 'Aplicadas', 'Omitidas', 'Fallidas'],
            [[
                $batch->status->getLabel(), $batch->rows_total, $batch->rows_new, $batch->rows_matched,
                $batch->rows_review, $batch->rows_applied, $batch->rows_skipped, $batch->rows_failed,
            ]],
        );
    }
}
