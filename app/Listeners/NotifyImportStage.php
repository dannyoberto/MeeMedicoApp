<?php

namespace App\Listeners;

use App\Domain\Directory\Actions\PublishDoctorsAction;
use App\Domain\Import\Events\ImportStageFailed;
use App\Domain\Import\Events\ImportStageFinished;
use App\Domain\Import\Jobs\RunImportStage;
use App\Filament\Resources\ImportBatches\ImportBatchResource;
use App\Models\ImportBatch;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Avisa en la campana del backoffice cuando una etapa del lote termina o falla.
 * Lo recibe quien la lanzó; si vino de la consola, quien subió el lote.
 */
class NotifyImportStage
{
    private const STAGE_LABELS = [
        RunImportStage::NORMALIZE => 'normalizar',
        RunImportStage::MATCH => 'buscar coincidencias',
        RunImportStage::APPLY => 'aplicar',
        RunImportStage::PUBLISH => 'publicar',
    ];

    public function handleFinished(ImportStageFinished $event): void
    {
        if (! ($batch = ImportBatch::find($event->batchId)) || ! ($user = $this->recipient($batch, $event->actorId))) {
            return;
        }

        $file = e($batch->file_name);
        $report = $event->report ?? [];

        [$title, $lines] = match ($event->stage) {
            RunImportStage::MATCH => ['Lote listo para revisar', [
                "{$file}: {$batch->rows_total} filas.",
                $batch->rows_review > 0 ? "{$batch->rows_review} en revisión: resuélvelas antes de aplicar." : 'Nada pendiente de revisión: ya se puede aplicar.',
            ]],
            RunImportStage::APPLY => ['Lote aplicado', [
                "{$file}: ".($report['created'] ?? 0).' fichas creadas en borrador, '.($report['updated'] ?? 0).' completadas, '.($report['failed'] ?? 0).' fallidas.',
                'Nada se publicó todavía.',
            ]],
            RunImportStage::PUBLISH => ['Fichas del lote publicadas', [
                "{$file}: ".($report['published'] ?? 0).' publicadas, '.($report['skipped'] ?? 0).' sin publicar.',
                ...array_map('e', PublishDoctorsAction::reasonLines(['reasons' => $report['reasons'] ?? []])),
            ]],
            default => [null, []],
        };

        if ($title === null) {
            return;
        }

        Notification::make()
            ->success()
            ->title($title)
            ->body(implode('<br>', $lines))
            ->actions([$this->openBatch($batch)])
            ->sendToDatabase($user);
    }

    public function handleFailed(ImportStageFailed $event): void
    {
        if (! ($batch = ImportBatch::find($event->batchId)) || ! ($user = $this->recipient($batch, $event->actorId))) {
            return;
        }

        $stage = self::STAGE_LABELS[$event->stage] ?? $event->stage;

        Notification::make()
            ->danger()
            ->title('Falló el lote '.e($batch->file_name))
            ->body(e("Etapa «{$stage}»: {$event->message}"))
            ->actions([$this->openBatch($batch)])
            ->sendToDatabase($user);
    }

    private function recipient(ImportBatch $batch, ?string $actorId): ?User
    {
        return User::find($actorId ?? $batch->created_by_user_id);
    }

    private function openBatch(ImportBatch $batch): Action
    {
        return Action::make('open')
            ->label('Abrir lote')
            ->button()
            ->url(ImportBatchResource::getUrl('view', ['record' => $batch]))
            ->markAsRead();
    }
}
