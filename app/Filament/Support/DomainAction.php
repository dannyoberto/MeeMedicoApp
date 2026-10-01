<?php

namespace App\Filament\Support;

use App\Domain\Directory\Exceptions\DirectoryRuleException;
use App\Domain\Identity\Exceptions\IdentityRuleException;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/**
 * Ejecuta una Action de dominio desde una acción de Filament y traduce sus reglas
 * rotas a una notificación legible, deteniendo la acción sin cerrar el modal.
 * Filament solo presenta: la regla vive en la Action.
 */
final class DomainAction
{
    public static function run(Action $action, Closure $operation): mixed
    {
        try {
            return $operation();
        } catch (DirectoryRuleException|IdentityRuleException $e) {
            self::notify($e->getMessage(), $e instanceof DirectoryRuleException ? $e->details : []);
        } catch (ValidationException $e) {
            self::notify('Revisa los datos', collect($e->errors())->flatten()->all());
        }

        $action->halt();
    }

    /**
     * @param  array<int|string, string>  $details
     */
    public static function notify(string $title, array $details = []): void
    {
        $body = $details === []
            ? null
            : new HtmlString(collect($details)->map(fn (string $d) => '• '.e($d))->implode('<br>'));

        Notification::make()->danger()->title($title)->body($body)->persistent()->send();
    }
}
