<?php

namespace App\Listeners;

use App\Domain\Directory\Actions\DoctorInsurersAction;
use App\Domain\Directory\Events\InsurerAppliedToDoctors;
use App\Filament\Resources\Doctors\DoctorResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Avisa en la campana del backoffice cuando termina una asignación de aseguradora
 * en lote que se hizo en segundo plano.
 */
class NotifyInsurerAppliedToDoctors
{
    public function handle(InsurerAppliedToDoctors $event): void
    {
        if (! $event->actorId || ! ($user = User::find($event->actorId))) {
            return;
        }

        Notification::make()
            ->status($event->report['skipped'] > 0 ? 'warning' : 'success')
            ->title(($event->attach ? 'Asignación' : 'Retirada')." de {$event->insurerName} terminada")
            ->body(implode('<br>', array_map('e', DoctorInsurersAction::reportLines($event->report, $event->attach))))
            ->actions([
                Action::make('open')
                    ->label('Ver médicos')
                    ->button()
                    ->url(DoctorResource::getUrl('index'))
                    ->markAsRead(),
            ])
            ->sendToDatabase($user);
    }
}
