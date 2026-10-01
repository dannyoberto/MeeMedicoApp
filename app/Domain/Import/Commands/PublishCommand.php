<?php

namespace App\Domain\Import\Commands;

use App\Domain\Import\Actions\PublishImportBatchAction;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Console\Command;

class PublishCommand extends Command
{
    protected $signature = 'import:publish
                            {batch : ID del lote}
                            {--user= : Correo de quien publica (queda en la auditoría)}';

    protected $description = 'Etapa 5: publica las fichas del lote que pasan la puerta de calidad';

    public function handle(PublishImportBatchAction $publish): int
    {
        $actor = $this->option('user') ? User::where('email', mb_strtolower($this->option('user')))->firstOrFail() : null;
        $report = $publish->execute(ImportBatch::findOrFail($this->argument('batch')), $actor);

        $this->info("Publicadas: {$report['published']} · sin publicar: {$report['skipped']}");
        foreach ($report['reasons'] as $reason => $count) {
            $this->line("  {$count} sin: {$reason}");
        }

        return self::SUCCESS;
    }
}
