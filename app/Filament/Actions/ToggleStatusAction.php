<?php

namespace App\Filament\Actions;

use App\Domain\Directory\Actions\SetCatalogStatusAction;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Baja lógica de catálogos: sustituye a DeleteAction, porque ninguna entidad del
 * directorio se borra físicamente (AGENTS.md §5). Autorizada por Policy::deactivate;
 * la regla (no desactivar lo que usa una ficha publicada) vive en SetCatalogStatusAction.
 */
final class ToggleStatusAction
{
    public static function make(): Action
    {
        $isActive = fn (Model $record): bool => $record->getAttribute('status')?->value === 'active';

        return Action::make('toggleStatus')
            ->label(fn (Model $record) => $isActive($record) ? 'Desactivar' : 'Activar')
            ->icon(fn (Model $record) => $isActive($record) ? Heroicon::OutlinedMinusCircle : Heroicon::OutlinedCheckCircle)
            ->color(fn (Model $record) => $isActive($record) ? 'gray' : 'success')
            ->requiresConfirmation()
            ->modalDescription(fn (Model $record) => $isActive($record)
                ? 'Deja de estar disponible para nuevas asignaciones y en el sitio público. Se puede reactivar.'
                : 'Vuelve a estar disponible.')
            ->authorize(fn (Model $record) => auth()->user()?->can('deactivate', $record) ?? false)
            ->action(function (Model $record, Action $action) use ($isActive) {
                try {
                    app(SetCatalogStatusAction::class)->execute($record, ! $isActive($record), auth()->user());
                } catch (DirectoryRuleException $e) {
                    Notification::make()->danger()->title('No se puede desactivar')->body($e->getMessage())->persistent()->send();
                    $action->halt();
                }

                Notification::make()->success()->title($isActive($record->refresh()) ? 'Activado' : 'Desactivado')->send();
            });
    }
}
