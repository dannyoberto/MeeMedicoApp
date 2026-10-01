<?php

namespace App\Domain\Import\Commands;

use App\Domain\Import\Actions\NormalizeImportBatchAction;
use App\Models\ImportBatch;
use Illuminate\Console\Command;

class NormalizeCommand extends Command
{
    protected $signature = 'import:normalize {batch : ID del lote}';

    protected $description = 'Etapa 2: normaliza las filas y las traduce al catálogo';

    public function handle(NormalizeImportBatchAction $action): int
    {
        $batch = $action->execute(ImportBatch::findOrFail($this->argument('batch')));

        BatchSummary::print($this, $batch);
        $this->line("Siguiente: php artisan import:match {$batch->id}");

        return self::SUCCESS;
    }
}
