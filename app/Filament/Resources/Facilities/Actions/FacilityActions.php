<?php

namespace App\Filament\Resources\Facilities\Actions;

use App\Domain\Directory\Actions\PublishFacilityAction;
use App\Domain\Directory\Actions\UnpublishFacilityAction;
use App\Domain\Directory\Enums\FacilityStatus;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use App\Domain\Directory\Support\FacilityPublicationRequirements;
use App\Filament\Support\DomainAction;
use App\Models\Facility;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

/**
 * Activar y desactivar establecimientos, uno a uno o en lote. Cada uno pasa por la misma
 * Action de dominio; la visibilidad solo evita ofrecer lo que no aplica al estado actual.
 */
final class FacilityActions
{
    /** Más que esto en una sola selección merece hacerse por partes. */
    public const BULK_LIMIT = 100;

    public static function publish(): Action
    {
        return Action::make('publish')
            ->label('Activar')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Activar establecimiento')
            ->modalDescription(function (Facility $record) {
                $missing = FacilityPublicationRequirements::missing($record);

                return $missing === []
                    ? 'Queda listo para publicarse cuando exista su página pública.'
                    : new HtmlString('No se puede activar todavía. Falta:<br>'.collect($missing)->map(fn ($m) => '• '.e($m))->implode('<br>'));
            })
            ->modalSubmitAction(fn (Action $action, Facility $record) => $action->disabled(FacilityPublicationRequirements::missing($record) !== []))
            ->visible(fn (Facility $record) => $record->status !== FacilityStatus::Active)
            ->authorize(fn (Facility $record) => auth()->user()?->can('publish', $record) ?? false)
            ->action(function (Facility $record, Action $action) {
                DomainAction::run($action, fn () => app(PublishFacilityAction::class)->execute($record, auth()->user()));
                Notification::make()->success()->title('Establecimiento activado')->send();
            });
    }

    public static function unpublish(): Action
    {
        return Action::make('unpublish')
            ->label('Desactivar')
            ->icon(Heroicon::OutlinedMinusCircle)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Sus sedes, contactos y médicos se conservan. Se puede volver a activar.')
            ->schema([Textarea::make('reason')->label('Motivo (opcional)')->maxLength(500)])
            ->visible(fn (Facility $record) => $record->status === FacilityStatus::Active)
            ->authorize(fn (Facility $record) => auth()->user()?->can('publish', $record) ?? false)
            ->action(function (array $data, Facility $record, Action $action) {
                DomainAction::run($action, fn () => app(UnpublishFacilityAction::class)->execute($record, auth()->user(), $data['reason'] ?? null));
                Notification::make()->success()->title('Establecimiento desactivado')->send();
            });
    }

    public static function bulkPublish(): BulkAction
    {
        return BulkAction::make('publishSelected')
            ->label('Activar seleccionados')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('primary')
            ->requiresConfirmation()
            ->modalDescription('Cada uno pasa por los mismos requisitos que al activarlo solo: los que no los cumplen se quedan como están y se informa por qué.')
            ->authorize(fn () => auth()->user()?->can('facilities.publish') ?? false)
            ->fetchSelectedRecords(false)
            ->deselectRecordsAfterCompletion()
            ->action(function (Builder $selectedRecordsQuery, BulkAction $action) {
                $facilities = self::selection($selectedRecordsQuery->where('status', '<>', FacilityStatus::Active), $action);

                $skipped = [];
                foreach ($facilities as $facility) {
                    try {
                        app(PublishFacilityAction::class)->execute($facility, auth()->user());
                    } catch (DirectoryRuleException $e) {
                        $skipped[] = e($facility->name).': '.e(implode(', ', $e->details));
                    }
                }

                $activated = $facilities->count() - count($skipped);
                Notification::make()
                    ->status($skipped === [] ? 'success' : 'warning')
                    ->title("{$activated} activados, ".count($skipped).' sin activar')
                    ->body($skipped === [] ? null : implode('<br>', $skipped))
                    ->persistent($skipped !== [])
                    ->send();
            });
    }

    public static function bulkUnpublish(): BulkAction
    {
        return BulkAction::make('unpublishSelected')
            ->label('Desactivar seleccionados')
            ->icon(Heroicon::OutlinedMinusCircle)
            ->color('gray')
            ->requiresConfirmation()
            ->schema([Textarea::make('reason')->label('Motivo (opcional)')->maxLength(500)])
            ->authorize(fn () => auth()->user()?->can('facilities.publish') ?? false)
            ->fetchSelectedRecords(false)
            ->deselectRecordsAfterCompletion()
            ->action(function (array $data, Builder $selectedRecordsQuery, BulkAction $action) {
                $facilities = self::selection($selectedRecordsQuery->where('status', FacilityStatus::Active), $action);

                foreach ($facilities as $facility) {
                    app(UnpublishFacilityAction::class)->execute($facility, auth()->user(), $data['reason'] ?? null);
                }

                Notification::make()->success()->title("{$facilities->count()} establecimientos desactivados")->send();
            });
    }

    /**
     * @param  Builder<Facility>  $query
     * @return Collection<int, Facility>
     */
    private static function selection(Builder $query, BulkAction $action): Collection
    {
        $facilities = $query->reorder()->limit(self::BULK_LIMIT + 1)->get();

        if ($facilities->count() > self::BULK_LIMIT) {
            DomainAction::notify('Demasiados establecimientos en la selección', ['Selecciona hasta '.self::BULK_LIMIT.' por vez.']);
            $action->halt();
        }

        return $facilities;
    }
}
