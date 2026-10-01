<?php

namespace App\Domain\Import\Support;

use App\Domain\Import\Enums\ImportBatchStatus;
use App\Domain\Import\Enums\ImportRowStatus;
use App\Models\ImportBatch;

/**
 * Recalcula los contadores del lote desde sus filas: la fuente de verdad son las
 * filas, los contadores son una copia para listar lotes sin agregar en cada vista.
 */
final class ImportBatchCounters
{
    public static function refresh(ImportBatch $batch, ?ImportBatchStatus $status = null): ImportBatch
    {
        $counts = $batch->rows()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $count = fn (ImportRowStatus ...$statuses) => (int) collect($statuses)->sum(fn ($s) => $counts[$s->value] ?? 0);

        $batch->forceFill([
            'rows_total' => (int) $counts->sum(),
            'rows_new' => $count(ImportRowStatus::New),
            'rows_matched' => $count(ImportRowStatus::Matched),
            'rows_review' => $count(ImportRowStatus::NeedsReview),
            'rows_applied' => $count(ImportRowStatus::Applied),
            'rows_skipped' => $count(ImportRowStatus::Skipped, ImportRowStatus::Suppressed),
            'rows_failed' => $count(ImportRowStatus::Failed),
        ]);

        if ($status) {
            $batch->status = $status;
        }

        $batch->save();

        return $batch;
    }
}
