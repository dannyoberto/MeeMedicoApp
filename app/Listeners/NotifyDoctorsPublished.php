<?php

namespace App\Listeners;

use App\Domain\Directory\Actions\PublishDoctorsAction;
use App\Domain\Directory\Events\DoctorsPublished;
use App\Filament\Resources\Doctors\DoctorResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Avisa en la campana del backoffice cuando termina una publicación en lote que se
 * hizo en segundo plano (más de 100 fichas seleccionadas).
 */
class NotifyDoctorsPublished
{
    public function handle(DoctorsPublished $event): void
    {
        if (! $event->actorId || ! ($user = User::find($event->actorId))) {
            return;
        }

        Notification::make()
            ->status($event->report['skipped'] > 0 ? 'warning' : 'success')
            ->title('Publicación en lote terminada')
            ->body(implode('<br>', [
                PublishDoctorsAction::summary($event->report),
                ...array_map('e', PublishDoctorsAction::reasonLines($event->report)),
            ]))
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
