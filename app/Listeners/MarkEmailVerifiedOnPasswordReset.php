<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Definir la contraseña con el enlace enviado al correo demuestra que la persona
 * controla esa dirección: cierra la invitación marcando el correo como verificado.
 */
class MarkEmailVerifiedOnPasswordReset
{
    public function handle(PasswordReset $event): void
    {
        $user = $event->user;

        if ($user instanceof User && $user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }
    }
}
