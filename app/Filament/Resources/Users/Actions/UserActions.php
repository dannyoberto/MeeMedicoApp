<?php

namespace App\Filament\Resources\Users\Actions;

use App\Domain\Identity\Actions\ReactivateUserAction;
use App\Domain\Identity\Actions\SendPasswordSetupLinkAction;
use App\Domain\Identity\Actions\SuspendUserAction;
use App\Domain\Identity\Actions\SyncUserRolesAction;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Exceptions\IdentityRuleException;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Acciones sobre una cuenta. Cada una delega en su Action de dominio; aquí solo
 * hay presentación, autorización por Policy y traducción de errores a notificaciones.
 */
final class UserActions
{
    public static function assignRoles(): Action
    {
        return Action::make('assignRoles')
            ->label('Asignar roles')
            ->icon(Heroicon::OutlinedKey)
            ->color('gray')
            ->modalDescription('Qué permite cada rol se define en PermissionSeeder; aquí solo se asigna.')
            ->schema([
                CheckboxList::make('roles')
                    ->label('Roles')
                    ->options(fn () => self::roleOptions())
                    ->default(fn (User $record) => $record->getRoleNames()->all()),
            ])
            ->authorize(fn (User $record) => auth()->user()?->can('manageRoles', $record) ?? false)
            ->action(function (array $data, User $record, Action $action) {
                self::guarded($action, fn () => app(SyncUserRolesAction::class)->execute($record, $data['roles'] ?? [], auth()->user()));
                Notification::make()->success()->title('Roles actualizados')->send();
            });
    }

    public static function suspend(): Action
    {
        return Action::make('suspend')
            ->label('Suspender')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Cierra sus sesiones abiertas y revoca sus tokens de inmediato. Se puede reactivar.')
            ->schema([
                Textarea::make('reason')
                    ->label('Motivo')
                    ->helperText('Queda en la auditoría.')
                    ->required()
                    ->maxLength(500),
            ])
            ->visible(fn (User $record) => $record->status !== UserStatus::Suspended)
            ->authorize(fn (User $record) => auth()->user()?->can('suspend', $record) ?? false)
            ->action(function (array $data, User $record, Action $action) {
                self::guarded($action, fn () => app(SuspendUserAction::class)->execute($record, auth()->user(), $data['reason']));
                Notification::make()->success()->title('Cuenta suspendida')->send();
            });
    }

    public static function reactivate(): Action
    {
        return Action::make('reactivate')
            ->label('Reactivar')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (User $record) => $record->status !== UserStatus::Active)
            ->authorize(fn (User $record) => auth()->user()?->can('suspend', $record) ?? false)
            ->action(function (User $record) {
                app(ReactivateUserAction::class)->execute($record, auth()->user());
                Notification::make()->success()->title('Cuenta reactivada')->send();
            });
    }

    public static function resendInvitation(): Action
    {
        return Action::make('resendInvitation')
            ->label('Enviar enlace de contraseña')
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Envía un enlace nuevo para definir la contraseña. El anterior deja de funcionar.')
            ->visible(fn (User $record) => $record->status === UserStatus::Active)
            ->authorize(fn (User $record) => auth()->user()?->can('update', $record) ?? false)
            ->action(function (User $record) {
                app(SendPasswordSetupLinkAction::class)->execute($record);
                Notification::make()->success()->title('Enlace enviado')->body("A {$record->email}")->send();
            });
    }

    /**
     * @return array<string, string>
     */
    public static function roleOptions(): array
    {
        return Role::orderBy('name')->pluck('name', 'name')->all();
    }

    /**
     * Las reglas de identidad (no dejar el backoffice sin admins, no suspenderse a sí
     * mismo) se muestran como notificación y detienen la acción sin cerrar el modal.
     */
    private static function guarded(Action $action, callable $operation): void
    {
        try {
            $operation();
        } catch (IdentityRuleException $e) {
            Notification::make()->danger()->title('No se puede completar')->body($e->getMessage())->persistent()->send();
            $action->halt();
        }
    }
}
