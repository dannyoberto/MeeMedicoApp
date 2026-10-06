<?php

namespace App\Filament\Resources\Doctors\Actions;

use App\Domain\Directory\Actions\DoctorInsurersAction;
use App\Domain\Directory\Actions\PublishDoctorsAction;
use App\Domain\Directory\Actions\UnpublishDoctorAction;
use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Enums\InsurerStatus;
use App\Domain\Directory\Jobs\ApplyInsurerToDoctorsJob;
use App\Domain\Directory\Jobs\PublishDoctorsJob;
use App\Models\Insurer;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
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

    /**
     * El caso real: una aseguradora entrega su red de médicos, el operador los filtra
     * y se la asigna a todos de una vez (MODULO-ESTABLECIMIENTOS-SEGUROS.md §7.2).
     */
    public static function assignInsurer(): BulkAction
    {
        return self::applyInsurer(true);
    }

    public static function removeInsurer(): BulkAction
    {
        return self::applyInsurer(false);
    }

    private static function applyInsurer(bool $attach): BulkAction
    {
        return BulkAction::make($attach ? 'assignInsurer' : 'removeInsurer')
            ->label($attach ? 'Asignar aseguradora' : 'Quitar aseguradora')
            ->icon($attach ? Heroicon::OutlinedShieldCheck : Heroicon::OutlinedShieldExclamation)
            ->color('gray')
            ->modalHeading($attach ? 'Asignar una aseguradora a los médicos seleccionados' : 'Quitar una aseguradora a los médicos seleccionados')
            ->modalDescription(($attach
                ? 'Solo se asigna a los médicos del país de la aseguradora; el resto se omite y se informa.'
                : 'Los médicos que no la tienen se quedan como están.')
                .' Con más de '.self::SYNC_LIMIT.' se hace en segundo plano y te avisamos en la campana.')
            ->modalSubmitActionLabel($attach ? 'Asignar' : 'Quitar')
            ->schema([
                Select::make('insurer_id')
                    ->label('Aseguradora')
                    ->options(fn () => Insurer::query()
                        ->when($attach, fn ($q) => $q->where('status', InsurerStatus::Active))
                        ->with('country:id,name')->orderBy('name')->get()
                        ->mapWithKeys(fn (Insurer $i) => [$i->id => "{$i->name} ({$i->country->name})"]))
                    ->searchable()
                    ->required(),
            ])
            ->authorize(fn () => auth()->user()?->can('doctors.update') ?? false)
            ->fetchSelectedRecords(false)
            ->deselectRecordsAfterCompletion()
            ->action(function (array $data, Builder $selectedRecordsQuery) use ($attach) {
                $insurer = Insurer::findOrFail($data['insurer_id']);
                $count = (clone $selectedRecordsQuery)->reorder()->count();

                if ($count > self::SYNC_LIMIT) {
                    ApplyInsurerToDoctorsJob::dispatch($insurer->id, (clone $selectedRecordsQuery)->reorder()->pluck('doctors.id')->all(), $attach, auth()->id());

                    Notification::make()->info()
                        ->title(($attach ? 'Asignando' : 'Quitando')." {$insurer->name} a {$count} médicos en segundo plano")
                        ->body('Te avisaremos en la campana al terminar.')
                        ->send();

                    return;
                }

                $report = app(DoctorInsurersAction::class)->applyToMany($insurer, $selectedRecordsQuery->get(), $attach, auth()->user());

                Notification::make()
                    ->status($report['skipped'] > 0 ? 'warning' : 'success')
                    ->title($insurer->name)
                    ->body(implode('<br>', array_map('e', DoctorInsurersAction::reportLines($report, $attach))))
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
