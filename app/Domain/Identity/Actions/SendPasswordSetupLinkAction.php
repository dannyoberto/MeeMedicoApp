<?php

namespace App\Domain\Identity\Actions;

use App\Models\User;
use App\Notifications\PasswordSetupNotification;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Password;

/**
 * Envía el enlace para definir contraseña. Reutiliza password_reset_tokens y la
 * página de restablecimiento del panel: sin tablas ni rutas propias.
 * Crear un token nuevo invalida el anterior, así que reenviar es seguro.
 */
class SendPasswordSetupLinkAction
{
    public function execute(User $user): void
    {
        $token = Password::broker()->createToken($user);
        $url = Filament::getPanel('admin')->getResetPasswordUrl($token, $user);

        $user->notify(new PasswordSetupNotification($url));
    }
}
