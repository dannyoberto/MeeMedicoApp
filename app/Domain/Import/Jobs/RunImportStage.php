<?php

namespace App\Domain\Import\Jobs;

use App\Domain\Import\Actions\ApplyImportBatchAction;
use App\Domain\Import\Actions\MatchImportBatchAction;
use App\Domain\Import\Actions\NormalizeImportBatchAction;
use App\Domain\Import\Actions\PublishImportBatchAction;
use App\Domain\Import\Enums\ImportBatchStatus;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use InvalidArgumentException;
use Throwable;

/**
 * Una etapa del pipeline sobre un lote, en cola (§12.3: un lote de 20.000 filas no
 * se procesa en una petición HTTP). Si falla, el lote queda en "failed" con el motivo.
 */
class RunImportStage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const NORMALIZE = 'normalize';

    public const MATCH = 'match';

    public const APPLY = 'apply';

    public const PUBLISH = 'publish';

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(
        public readonly string $batchId,
        public readonly string $stage,
        public readonly ?string $actorId = null,
    ) {}

    public function handle(): void
    {
        $batch = ImportBatch::findOrFail($this->batchId);
        $actor = $this->actorId ? User::find($this->actorId) : null;

        match ($this->stage) {
            self::NORMALIZE => app(NormalizeImportBatchAction::class)->execute($batch),
            self::MATCH => app(MatchImportBatchAction::class)->execute($batch),
            self::APPLY => app(ApplyImportBatchAction::class)->execute($batch, $actor),
            self::PUBLISH => app(PublishImportBatchAction::class)->execute($batch, $actor),
            default => throw new InvalidArgumentException("Etapa desconocida: {$this->stage}"),
        };
    }

    public function failed(Throwable $e): void
    {
        $batch = ImportBatch::find($this->batchId);

        $batch?->update([
            'status' => ImportBatchStatus::Failed,
            'notes' => trim(($batch->notes ? $batch->notes."\n\n" : '')."Falló la etapa {$this->stage}: {$e->getMessage()}"),
        ]);
    }
}
