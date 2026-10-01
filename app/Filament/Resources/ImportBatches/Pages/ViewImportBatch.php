<?php

namespace App\Filament\Resources\ImportBatches\Pages;

use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Import\Enums\ImportBatchStatus;
use App\Domain\Import\Enums\ImportRowStatus;
use App\Domain\Import\ImportPipeline;
use App\Filament\Resources\ImportBatches\ImportBatchResource;
use App\Filament\Resources\ImportBatches\Widgets\ImportBatchStats;
use App\Models\Doctor;
use App\Models\ImportBatch;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * @property ImportBatch $record
 */
class ViewImportBatch extends ViewRecord
{
    protected static string $resource = ImportBatchResource::class;

    /** Estados en los que una etapa está corriendo: no se lanza otra encima. */
    private const RUNNING = [ImportBatchStatus::Ingesting, ImportBatchStatus::Normalizing, ImportBatchStatus::Matching, ImportBatchStatus::Applying];

    public function getTitle(): string
    {
        return $this->record->file_name;
    }

    protected function getHeaderWidgets(): array
    {
        return [ImportBatchStats::class];
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
                    app(ImportPipeline::class)->process($this->record);
                    Notification::make()->success()->title('Reprocesando el lote')->send();
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
                    Notification::make()->success()->title('Aplicando el lote')->body('Las fichas se crean en borrador. Nada se publica todavía.')->send();
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
                    Notification::make()->success()->title('Publicando las fichas del lote')->body('El resultado aparecerá en las notas del lote.')->send();
                }),
        ];
    }

    private function isRunning(): bool
    {
        return in_array($this->record->refresh()->status, self::RUNNING, true);
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
