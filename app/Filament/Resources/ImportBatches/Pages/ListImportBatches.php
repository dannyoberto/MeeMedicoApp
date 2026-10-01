<?php

namespace App\Filament\Resources\ImportBatches\Pages;

use App\Domain\Import\Template\TemplateWriter;
use App\Filament\Resources\ImportBatches\ImportBatchResource;
use App\Models\Country;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListImportBatches extends ListRecords
{
    protected static string $resource = ImportBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadTemplate')
                ->label('Descargar plantilla')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->modalDescription('Se genera con el catálogo actual (especialidades, ciudades y provincias del país). Si el catálogo cambia, descárgala de nuevo.')
                ->schema([
                    Select::make('country_id')->label('País')->options(Country::orderBy('name')->pluck('name', 'id'))->required(),
                ])
                ->action(function (array $data) {
                    $country = Country::findOrFail($data['country_id']);
                    $path = storage_path('app/private/tmp/plantilla-'.uniqid().'.xlsx');
                    app(TemplateWriter::class)->write($country, $path);

                    return response()->download($path, "plantilla-medicos-{$country->slug}.xlsx")->deleteFileAfterSend();
                }),
            CreateAction::make()->label('Subir lote')->icon(Heroicon::OutlinedArrowUpTray),
        ];
    }
}
