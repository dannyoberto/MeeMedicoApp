<?php

namespace App\Domain\Import\Commands;

use App\Domain\Import\Template\SampleWriter;
use App\Models\Country;
use Illuminate\Console\Command;

class MakeImportSampleCommand extends Command
{
    protected $signature = 'import:sample
                            {country : Código ISO del país (CR, GT, DO, VE)}
                            {--output= : Ruta del archivo; por defecto storage/app/import-templates/}';

    protected $description = 'Genera una plantilla llena con médicos SINTÉTICOS para probar la importación';

    public function handle(SampleWriter $writer): int
    {
        $country = Country::where('code', strtoupper($this->argument('country')))->first();

        if (! $country) {
            $this->error('País desconocido. Usa: '.Country::orderBy('code')->pluck('code')->implode(', '));

            return self::FAILURE;
        }

        $path = $this->option('output') ?: storage_path("app/import-templates/muestra-medicos-{$country->slug}.xlsx");
        $cases = $writer->write($country, $path);

        $this->info("Muestra sintética de {$country->name}: {$path}");
        $this->line('Casos incluidos a propósito:');
        foreach ($cases as $case) {
            $this->line("  • {$case}");
        }

        return self::SUCCESS;
    }
}
