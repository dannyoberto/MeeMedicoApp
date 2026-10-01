<?php

namespace App\Filament\Resources\DoctorSuppressions\Pages;

use App\Domain\Directory\Actions\RevokeSuppressionAction;
use App\Domain\Directory\Actions\UnpublishDoctorAction;
use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Support\SuppressionMatches;
use App\Filament\Resources\DoctorSuppressions\DoctorSuppressionResource;
use App\Filament\Support\DomainAction;
use App\Models\Doctor;
use App\Models\DoctorSuppression;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * @property DoctorSuppression $record
 */
class ViewDoctorSuppression extends ViewRecord
{
    protected static string $resource = DoctorSuppressionResource::class;

    /**
     * Sin EditAction: una supresión no se edita. Sí se retiran del sitio las fichas
     * existentes que coinciden (la supresión solo impide que se recreen o se publiquen),
     * y se revoca si la persona quiere volver.
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
                ->visible(fn () => ! $this->record->isRevoked() && $published()->isNotEmpty())
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
            Action::make('revoke')
                ->label('Revocar supresión')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('warning')
                ->modalHeading('Revocar supresión')
                ->modalDescription('La persona pidió volver a aparecer en MeeMedico. Podrá crearse e importarse de nuevo, pero las fichas despublicadas no vuelven solas: publícalas desde su ficha. La supresión queda en el historial como revocada.')
                ->modalSubmitActionLabel('Revocar')
                ->schema([
                    DateTimePicker::make('requested_at')
                        ->label('Fecha de la solicitud')
                        ->default(now())
                        ->maxDate(now())
                        ->required(),
                    Textarea::make('reason')
                        ->label('Canal y verificación de identidad')
                        ->helperText('Por dónde llegó la solicitud y cómo se comprobó que es la misma persona.')
                        ->maxLength(255)
                        ->required(),
                ])
                ->visible(fn () => auth()->user()?->can('revoke', $this->record) ?? false)
                ->authorize(fn () => auth()->user()?->can('revoke', $this->record) ?? false)
                ->action(function (array $data, Action $action) {
                    DomainAction::run($action, fn () => app(RevokeSuppressionAction::class)->execute(
                        $this->record, $data['reason'] ?? null, Carbon::parse($data['requested_at']), auth()->user(),
                    ));
                    Notification::make()->success()->title('Supresión revocada')
                        ->body('Ya no bloquea. Publica las fichas desde la lista de coincidencias.')->send();
                }),
        ];
    }
}
