<?php

namespace App\Filament\Resources\Doctors\Actions;

use App\Domain\Directory\Actions\LiftDoctorSuspensionAction;
use App\Domain\Directory\Actions\PublishDoctorAction;
use App\Domain\Directory\Actions\RejectVerificationAction;
use App\Domain\Directory\Actions\SuspendDoctorAction;
use App\Domain\Directory\Actions\UnpublishDoctorAction;
use App\Domain\Directory\Actions\VerifyDoctorAction;
use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Enums\VerificationSource;
use App\Domain\Directory\Enums\VerificationStatus;
use App\Domain\Directory\Support\PublicationRequirements;
use App\Filament\Support\DomainAction;
use App\Models\Doctor;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

use function Illuminate\Support\enum_value;

/**
 * Acciones del ciclo de vida de una ficha. Cada una delega en su Action de dominio;
 * la visibilidad solo evita ofrecer lo que no aplica al estado actual.
 */
final class DoctorActions
{
    /**
     * @return array<int, Action>
     */
    public static function all(): array
    {
        return [
            self::publish(),
            self::unpublish(),
            self::verify(),
            self::rejectVerification(),
            self::suspend(),
            self::liftSuspension(),
        ];
    }

    public static function publish(): Action
    {
        return Action::make('publish')
            ->label('Publicar')
            ->icon(Heroicon::OutlinedGlobeAlt)
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Publicar ficha')
            ->modalDescription(function (Doctor $record) {
                $missing = PublicationRequirements::missing($record);

                return $missing === []
                    ? 'La ficha será visible e indexable en el sitio público.'
                    : new HtmlString('No se puede publicar todavía. Falta:<br>'.collect($missing)->map(fn ($m) => '• '.e($m))->implode('<br>'));
            })
            ->modalSubmitAction(fn (Action $action, Doctor $record) => $action->disabled(PublicationRequirements::missing($record) !== []))
            ->visible(fn (Doctor $record) => in_array($record->status, [DoctorStatus::Draft, DoctorStatus::Inactive], true))
            ->authorize(fn (Doctor $record) => auth()->user()?->can('publish', $record) ?? false)
            ->action(function (Doctor $record, Action $action) {
                DomainAction::run($action, fn () => app(PublishDoctorAction::class)->execute($record, auth()->user()));
                Notification::make()->success()->title('Ficha publicada')->send();
            });
    }

    public static function unpublish(): Action
    {
        return Action::make('unpublish')
            ->label('Despublicar')
            ->icon(Heroicon::OutlinedEyeSlash)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Deja de verse en el sitio público. Se puede volver a publicar.')
            ->schema([Textarea::make('reason')->label('Motivo (opcional)')->maxLength(500)])
            ->visible(fn (Doctor $record) => $record->status === DoctorStatus::Active)
            ->authorize(fn (Doctor $record) => auth()->user()?->can('publish', $record) ?? false)
            ->action(function (array $data, Doctor $record, Action $action) {
                DomainAction::run($action, fn () => app(UnpublishDoctorAction::class)->execute($record, auth()->user(), $data['reason'] ?? null));
                Notification::make()->success()->title('Ficha despublicada')->send();
            });
    }

    public static function verify(): Action
    {
        return Action::make('verify')
            ->label('Verificar')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('success')
            ->modalHeading('Verificar identidad profesional')
            ->modalDescription(fn (Doctor $record) => "Confirmas que el colegiado {$record->license_number} figura en el registro oficial y corresponde a esta persona. Queda registrado quién verificó, cuándo y con qué.")
            ->schema([
                Select::make('source')
                    ->label('Cómo se contrastó')
                    ->options(VerificationSource::class)
                    ->required(),
                Textarea::make('notes')->label('Notas (opcional)')->maxLength(500),
            ])
            ->visible(fn (Doctor $record) => $record->verification_status !== VerificationStatus::Verified && filled($record->license_number))
            ->authorize(fn (Doctor $record) => auth()->user()?->can('verify', $record) ?? false)
            ->action(function (array $data, Doctor $record, Action $action) {
                DomainAction::run($action, fn () => app(VerifyDoctorAction::class)->execute(
                    $record, VerificationSource::from(enum_value($data['source'])), auth()->user(), $data['notes'] ?? null,
                ));
                Notification::make()->success()->title('Ficha verificada')->send();
            });
    }

    public static function rejectVerification(): Action
    {
        return Action::make('rejectVerification')
            ->label('Rechazar verificación')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->schema([Textarea::make('reason')->label('Motivo')->required()->maxLength(500)])
            ->visible(fn (Doctor $record) => in_array($record->verification_status, [VerificationStatus::Pending, VerificationStatus::Verified], true))
            ->authorize(fn (Doctor $record) => auth()->user()?->can('verify', $record) ?? false)
            ->action(function (array $data, Doctor $record, Action $action) {
                DomainAction::run($action, fn () => app(RejectVerificationAction::class)->execute($record, auth()->user(), $data['reason']));
                Notification::make()->success()->title('Verificación rechazada')->send();
            });
    }

    public static function suspend(): Action
    {
        return Action::make('suspend')
            ->label('Suspender')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Retira la ficha del sitio por una causa (queja, suplantación, orden de retirada). Para volver a publicarla hay que levantar la suspensión.')
            ->schema([Textarea::make('reason')->label('Motivo')->required()->maxLength(500)])
            ->visible(fn (Doctor $record) => ! in_array($record->status, [DoctorStatus::Suspended, DoctorStatus::Merged], true))
            ->authorize(fn (Doctor $record) => auth()->user()?->can('suspend', $record) ?? false)
            ->action(function (array $data, Doctor $record, Action $action) {
                DomainAction::run($action, fn () => app(SuspendDoctorAction::class)->execute($record, auth()->user(), $data['reason']));
                Notification::make()->success()->title('Ficha suspendida')->send();
            });
    }

    public static function liftSuspension(): Action
    {
        return Action::make('liftSuspension')
            ->label('Levantar suspensión')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('La ficha queda despublicada; publicarla es un paso aparte.')
            ->visible(fn (Doctor $record) => $record->status === DoctorStatus::Suspended)
            ->authorize(fn (Doctor $record) => auth()->user()?->can('suspend', $record) ?? false)
            ->action(function (Doctor $record) {
                app(LiftDoctorSuspensionAction::class)->execute($record, auth()->user());
                Notification::make()->success()->title('Suspensión levantada')->send();
            });
    }
}
