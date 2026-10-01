<?php

namespace App\Filament\Resources\ImportBatches\Widgets;

use App\Models\ImportBatch;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

/**
 * Contadores del lote, refrescados mientras las etapas corren en cola.
 */
class ImportBatchStats extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected ?string $pollingInterval = '5s';

    protected function getStats(): array
    {
        /** @var ImportBatch $batch */
        $batch = $this->record->refresh();

        return [
            Stat::make('Estado', $batch->status->getLabel())->color($batch->status->getColor()),
            Stat::make('Filas', $batch->rows_total)
                ->description("{$batch->rows_new} nuevas · {$batch->rows_matched} coinciden con fichas existentes"),
            Stat::make('En revisión', $batch->rows_review)
                ->description($batch->rows_review > 0 ? 'Resuélvelas abajo antes de aplicar' : 'Nada pendiente')
                ->color($batch->rows_review > 0 ? 'warning' : 'gray'),
            Stat::make('Aplicadas', $batch->rows_applied)
                ->description("{$batch->rows_skipped} omitidas o suprimidas · {$batch->rows_failed} fallidas")
                ->color($batch->rows_failed > 0 ? 'danger' : 'gray'),
        ];
    }
}
