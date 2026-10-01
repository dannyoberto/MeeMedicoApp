<?php

namespace App\Domain\Import\Commands;

use App\Domain\Import\Actions\MatchImportBatchAction;
use App\Models\ImportBatch;
use Illuminate\Console\Command;

class MatchCommand extends Command
{
    protected $signature = 'import:match {batch : ID del lote}';

    protected $description = 'Etapa 3: busca coincidencias con fichas existentes (nunca fusiona)';

    public function handle(MatchImportBatchAction $action): int
    {
        $batch = $action->execute(ImportBatch::findOrFail($this->argument('batch')));

        BatchSummary::print($this, $batch);
        $this->line("Revisa las filas dudosas en el backoffice y después: php artisan import:apply {$batch->id}");

        return self::SUCCESS;
    }
}
