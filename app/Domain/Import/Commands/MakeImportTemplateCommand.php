<?php

namespace App\Domain\Import\Commands;

use App\Domain\Import\Template\TemplateWriter;
use App\Models\Country;
use Illuminate\Console\Command;

class MakeImportTemplateCommand extends Command
{
    protected $signature = 'import:template
                            {country : Código ISO del país (CR, GT, DO, VE)}
                            {--output= : Ruta del archivo; por defecto storage/app/import-templates/}';

    protected $description = 'Genera la plantilla Excel de carga masiva de médicos de un país';

    public function handle(TemplateWriter $writer): int
    {
        $country = Country::where('code', strtoupper($this->argument('country')))->first();

        if (! $country) {
            $this->error('País desconocido. Usa: '.Country::orderBy('code')->pluck('code')->implode(', '));

            return self::FAILURE;
        }

        $path = $this->option('output')
            ?: storage_path("app/import-templates/plantilla-medicos-{$country->slug}.xlsx");

        $writer->write($country, $path);

        $cities = $country->cities()->count();
        $this->info("Plantilla de {$country->name}: {$path}");
        if ($cities === 0) {
            $this->warn("{$country->name} no tiene ciudades cargadas: la columna Ciudad quedará como texto libre.");
        }

        return self::SUCCESS;
    }
}
