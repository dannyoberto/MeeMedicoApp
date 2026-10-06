<?php

namespace App\Filament\Resources\Facilities\RelationManagers\Concerns;

use App\Filament\Support\DomainAction;
use App\Models\Facility;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Común a las pestañas del establecimiento: ejecuta la Action de dominio, traduce sus
 * reglas a notificación y avisa a la página para refrescar la lista de requisitos.
 */
trait ChangesFacilityAggregate
{
    protected function facility(): Facility
    {
        /** @var Facility */
        return $this->getOwnerRecord();
    }

    protected function canChangeAggregate(): bool
    {
        return auth()->user()?->can('update', $this->facility()) ?? false;
    }

    protected function changeAggregate(Action $action, Closure $operation, string $success): void
    {
        DomainAction::run($action, $operation);

        $this->dispatch('facility-aggregate-changed');
        Notification::make()->success()->title($success)->send();
    }
}
