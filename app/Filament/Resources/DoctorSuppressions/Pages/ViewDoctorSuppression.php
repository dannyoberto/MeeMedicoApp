<?php

namespace App\Filament\Resources\DoctorSuppressions\Pages;

use App\Domain\Directory\Actions\UnpublishDoctorAction;
use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Support\SuppressionMatches;
use App\Filament\Resources\DoctorSuppressions\DoctorSuppressionResource;
use App\Models\Doctor;
use App\Models\DoctorSuppression;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * @property DoctorSuppression $record
 */
class ViewDoctorSuppression extends ViewRecord
{
    protected static string $resource = DoctorSuppressionResource::class;

    /**
     * Sin EditAction: una supresión no se edita. Sí se retiran del sitio las fichas
     * existentes que coinciden: la supresión solo impide que el importador las recree.
     */
    protected function getHeaderActions(): array
    {
        $published = fn () => SuppressionMatches::for($this->record)->filter(fn ($d) => $d->status === DoctorStatus::Active);

        return [
            Action::make('unpublishMatches')
                ->label(fn () => 'Despublicar coincidencias ('.$published()->count().')')
                ->icon(Heroicon::OutlinedEyeSlash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('Retira del sitio público las fichas publicadas que coinciden con esta supresión. Revisa antes la lista: una coincidencia solo por nombre puede ser un homónimo.')
                ->visible(fn () => $published()->isNotEmpty())
                ->authorize(fn () => auth()->user()?->can('doctors.publish') ?? false)
                ->action(function () use ($published) {
                    $count = 0;
                    // Las coincidencias se cargan con columnas mínimas para mostrarlas;
                    // la Action recibe la ficha completa.
                    foreach (Doctor::whereKey($published()->modelKeys())->get() as $doctor) {
                        app(UnpublishDoctorAction::class)->execute($doctor, auth()->user(), 'Supresión '.$this->record->getKey());
                        $count++;
                    }
                    Notification::make()->success()->title("{$count} ficha(s) despublicada(s)")->send();
                }),
        ];
    }
}
