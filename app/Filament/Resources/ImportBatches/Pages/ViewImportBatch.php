<?php

namespace App\Filament\Resources\ImportBatches\Pages;

use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Import\Enums\ImportRowStatus;
use App\Domain\Import\ImportPipeline;
use App\Filament\Resources\ImportBatches\ImportBatchResource;
use App\Filament\Resources\ImportBatches\Widgets\ImportBatchStats;
use App\Models\Doctor;
use App\Models\ImportBatch;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\On;

/**
 * @property ImportBatch $record
 *
 * Las etapas corren en cola. Lanzar una avisa con `import-batch-changed` a los contadores
 * y a la tabla de filas para que se refresquen mientras corre; al terminar, los
 * contadores avisan con `import-batch-finished` y la cabecera muestra el siguiente paso
 * sin recargar.
 */
class ViewImportBatch extends ViewRecord
{
    protected static string $resource = ImportBatchResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    public function getTitle(): string
    {
        return $this->record->file_name;
    }

    protected function getHeaderWidgets(): array
    {
        return [ImportBatchStats::class];
    }

    #[On('import-batch-finished')]
    public function refreshBatch(): void
    {
        $this->record->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reprocess')
                ->label('Reprocesar')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Vuelve a normalizar y buscar coincidencias con el catálogo y los alias actuales. No toca filas ya aplicadas ni decisiones tomadas.')
                ->visible(fn () => ! $this->isRunning())
                ->authorize(fn () => auth()->user()?->can('resolve', $this->record) ?? false)
                ->action(function () {
                    app(ImportPipeline::class)->process($this->record, auth()->user());
                    $this->dispatch('import-batch-changed');
                    Notification::make()->success()->title('Reprocesando el lote')->body('Te avisaremos en la campana al terminar.')->send();
                }),

            Action::make('apply')
                ->label('Aplicar')
                ->icon(Heroicon::OutlinedCheck)
                ->requiresConfirmation()
                ->modalHeading('Aplicar el lote')
                ->modalDescription(fn () => $this->applySummary())
                ->visible(fn () => ! $this->isRunning() && $this->readyRows() > 0)
                ->authorize(fn () => auth()->user()?->can('apply', $this->record) ?? false)
                ->action(function () {
                    app(ImportPipeline::class)->apply($this->record, auth()->user());
                    $this->dispatch('import-batch-changed');
                    Notification::make()->success()->title('Aplicando el lote')->body('Las fichas se crean en borrador. Nada se publica todavía. Te avisaremos al terminar.')->send();
                }),

            Action::make('publish')
                ->label('Publicar fichas')
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Publicar las fichas del lote')
                ->modalDescription(fn () => "Se intentará publicar {$this->draftDoctors()} ficha(s) en borrador creadas por este lote. Solo se publican las que tienen especialidad, consultorio con ciudad y un contacto público; el resto queda en borrador y se informa por qué.")
                ->visible(fn () => ! $this->isRunning() && $this->draftDoctors() > 0)
                ->authorize(fn () => auth()->user()?->can('doctors.publish') ?? false)
                ->action(function () {
                    app(ImportPipeline::class)->publish($this->record, auth()->user());
                    $this->dispatch('import-batch-changed');
                    Notification::make()->success()->title('Publicando las fichas del lote')->body('Te avisaremos en la campana con el resultado.')->send();
                }),
        ];
    }

    private function isRunning(): bool
    {
        return $this->record->refresh()->status->isRunning();
    }

    private function readyRows(): int
    {
        return $this->record->rows()->whereIn('status', [ImportRowStatus::New, ImportRowStatus::Matched, ImportRowStatus::Approved])->count();
    }

    private function draftDoctors(): int
    {
        return Doctor::where('import_batch_id', $this->record->getKey())->where('status', DoctorStatus::Draft)->count();
    }

    private function applySummary(): string
    {
        $review = $this->record->rows()->where('status', ImportRowStatus::NeedsReview)->count();

        return "Se aplicarán {$this->readyRows()} fila(s) listas: las fichas nuevas se crean en BORRADOR y las existentes solo se completan (nunca se sobrescriben)."
            .($review > 0 ? " Quedan {$review} en revisión: no se aplican hasta que las resuelvas." : '');
    }
}
