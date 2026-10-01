<?php

namespace App\Filament\Resources\Doctors\RelationManagers\Concerns;

use App\Filament\Support\DomainAction;
use App\Models\Doctor;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Común a los gestores de relaciones del médico: ejecuta la Action de dominio,
 * traduce sus reglas a notificación y avisa a la página de edición para refrescar
 * la lista de requisitos para publicar.
 */
trait ChangesDoctorAggregate
{
    protected function doctor(): Doctor
    {
        /** @var Doctor */
        return $this->getOwnerRecord();
    }

    protected function canChangeAggregate(): bool
    {
        return auth()->user()?->can('update', $this->doctor()) ?? false;
    }

    protected function changeAggregate(Action $action, Closure $operation, string $success): void
    {
        DomainAction::run($action, $operation);

        $this->dispatch('doctor-aggregate-changed');
        Notification::make()->success()->title($success)->send();
    }
}
