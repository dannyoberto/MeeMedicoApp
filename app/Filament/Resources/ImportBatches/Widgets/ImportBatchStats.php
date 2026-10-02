<?php

namespace App\Filament\Resources\ImportBatches\Widgets;

use App\Models\ImportBatch;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

/**
 * Contadores del lote. Solo se refrescan mientras una etapa corre en cola (o acaba de
 * lanzarse y aún no la tomó el worker); un lote quieto no consulta la base cada 5 s.
 * Al terminar una etapa avisan con `import-batch-finished` a la página y a la tabla.
 */
class ImportBatchStats extends StatsOverviewWidget
{
    public ?Model $record = null;

    /** Hasta cuándo vigilar una etapa recién lanzada (timestamp). */
    public ?int $watchUntil = null;

    public bool $wasRunning = false;

    public function mount(): void
    {
        // Recién subido, el lote ya está normalizando: hay que vigilarlo desde el principio.
        $this->wasRunning = $this->record?->status?->isRunning() ?? false;
    }

    #[On('import-batch-changed')]
    public function watch(): void
    {
        $this->watchUntil = now()->addMinutes(10)->getTimestamp();
    }

    protected function getPollingInterval(): ?string
    {
        $watching = $this->watchUntil !== null && now()->getTimestamp() < $this->watchUntil;

        return $watching || $this->wasRunning ? '5s' : null;
    }

    protected function getStats(): array
    {
        /** @var ImportBatch $batch */
        $batch = $this->record->refresh();
        $running = $batch->status->isRunning();

        if ($running) {
            $this->wasRunning = true;
            $this->watchUntil = null;
        } elseif ($this->wasRunning) {
            $this->wasRunning = false;
            $this->dispatch('import-batch-finished');
        }

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
