<?php

namespace App\Filament\Resources\Doctors\Actions;

use App\Domain\Directory\Actions\PublishDoctorsAction;
use App\Domain\Directory\Actions\UnpublishDoctorAction;
use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Jobs\PublishDoctorsJob;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Acciones sobre varias fichas a la vez. Cada ficha pasa por la misma Action que al
 * hacerlo una a una: aquí solo se decide si se hace ya o en segundo plano.
 *
 * Reciben la consulta de lo seleccionado, no los modelos: "seleccionar todo" en una
 * cola de miles no los carga en memoria.
 */
final class DoctorBulkActions
{
    /** Por encima de esto, publicar va a la cola y avisa al terminar. */
    public const SYNC_LIMIT = 100;

    public static function publish(): BulkAction
    {
        return BulkAction::make('publishSelected')
            ->label('Publicar seleccionadas')
            ->icon(Heroicon::OutlinedGlobeAlt)
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Publicar las fichas seleccionadas')
            ->modalDescription('Cada ficha pasa por los mismos requisitos que al publicarla sola: las que no los cumplen se quedan como están y se informa por qué. Con más de '.self::SYNC_LIMIT.' se publican en segundo plano y te avisamos en la campana.')
            ->modalSubmitActionLabel('Publicar')
            ->authorize(fn () => auth()->user()?->can('doctors.publish') ?? false)
            ->fetchSelectedRecords(false)
            ->deselectRecordsAfterCompletion()
            ->action(function (Builder $selectedRecordsQuery) {
                $count = (clone $selectedRecordsQuery)->reorder()->count();

                if ($count > self::SYNC_LIMIT) {
                    PublishDoctorsJob::dispatch((clone $selectedRecordsQuery)->reorder()->pluck('doctors.id')->all(), auth()->id());

                    Notification::make()->info()
                        ->title("Publicando {$count} fichas en segundo plano")
                        ->body('Te avisaremos en la campana al terminar.')
                        ->send();

                    return;
                }

                $report = app(PublishDoctorsAction::class)->execute($selectedRecordsQuery->get(), auth()->user());

                Notification::make()
                    ->status($report['skipped'] > 0 ? 'warning' : 'success')
                    ->title('Publicación en lote')
                    ->body(implode('<br>', [
                        PublishDoctorsAction::summary($report),
                        ...array_map('e', PublishDoctorsAction::reasonLines($report)),
                    ]))
                    ->persistent($report['skipped'] > 0)
                    ->send();
            });
    }

    public static function unpublish(): BulkAction
    {
        return BulkAction::make('unpublishSelected')
            ->label('Despublicar seleccionadas')
            ->icon(Heroicon::OutlinedEyeSlash)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Despublicar las fichas seleccionadas')
            ->modalDescription('Dejan de verse en el sitio público y se pueden volver a publicar. Solo afecta a las publicadas, hasta '.self::SYNC_LIMIT.' por vez: retirar muchas fichas a la vez merece una revisión.')
            ->modalSubmitActionLabel('Despublicar')
            ->schema([Textarea::make('reason')->label('Motivo (opcional)')->maxLength(500)])
            ->authorize(fn () => auth()->user()?->can('doctors.publish') ?? false)
            ->fetchSelectedRecords(false)
            ->deselectRecordsAfterCompletion()
            ->action(function (array $data, Builder $selectedRecordsQuery, BulkAction $action) {
                $doctors = (clone $selectedRecordsQuery)->where('status', DoctorStatus::Active)->reorder()->limit(self::SYNC_LIMIT + 1)->get();

                if ($doctors->count() > self::SYNC_LIMIT) {
                    Notification::make()->danger()
                        ->title('Demasiadas fichas publicadas en la selección')
                        ->body('Selecciona hasta '.self::SYNC_LIMIT.' por vez.')
                        ->send();
                    $action->halt();
                }

                foreach ($doctors as $doctor) {
                    app(UnpublishDoctorAction::class)->execute($doctor, auth()->user(), $data['reason'] ?? null);
                }

                Notification::make()->success()->title("{$doctors->count()} fichas despublicadas")->send();
            });
    }
}
