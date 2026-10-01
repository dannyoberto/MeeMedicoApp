<?php

namespace App\Domain\Import\Commands;

use App\Domain\Import\Actions\IngestImportFileAction;
use App\Domain\Import\Exceptions\ImportFileException;
use App\Models\Country;
use Illuminate\Console\Command;

class IngestCommand extends Command
{
    protected $signature = 'import:ingest
                            {file : Ruta del Excel (plantilla de MeeMedico)}
                            {--country= : Código ISO del país (CR, GT, DO, VE)}
                            {--source= : Fuente del lote; por defecto config(import.default_source)}';

    protected $description = 'Etapa 1: crea el lote y vuelca las filas crudas en staging';

    public function handle(IngestImportFileAction $ingest): int
    {
        $country = Country::where('code', strtoupper((string) $this->option('country')))->first();
        if (! $country) {
            $this->error('Indica --country con un código válido: '.Country::orderBy('code')->pluck('code')->implode(', '));

            return self::FAILURE;
        }

        $file = $this->argument('file');
        if (! is_file($file)) {
            $this->error("No existe el archivo {$file}");

            return self::FAILURE;
        }

        try {
            $batch = $ingest->execute($file, basename($file), $country, $this->option('source') ?: config('import.default_source'), null);
        } catch (ImportFileException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Lote {$batch->id}: {$batch->rows_total} filas leídas.");
        $this->line("Siguiente: php artisan import:normalize {$batch->id}");

        return self::SUCCESS;
    }
}
