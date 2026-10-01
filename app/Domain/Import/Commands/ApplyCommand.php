<?php

namespace App\Domain\Import\Commands;

use App\Domain\Import\Actions\ApplyImportBatchAction;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Console\Command;

class ApplyCommand extends Command
{
    protected $signature = 'import:apply
                            {batch : ID del lote}
                            {--user= : Correo de quien aplica (queda en la auditoría)}';

    protected $description = 'Etapa 4: crea o completa fichas en BORRADOR. Nunca publica';

    public function handle(ApplyImportBatchAction $apply): int
    {
        $actor = $this->option('user') ? User::where('email', mb_strtolower($this->option('user')))->firstOrFail() : null;
        $report = $apply->execute(ImportBatch::findOrFail($this->argument('batch')), $actor);

        $this->info("Fichas creadas: {$report['created']} · completadas: {$report['updated']} · fallidas: {$report['failed']}");
        $this->line('Siguiente (aparte, cuando lo decidas): php artisan import:publish '.$this->argument('batch'));

        return self::SUCCESS;
    }
}
