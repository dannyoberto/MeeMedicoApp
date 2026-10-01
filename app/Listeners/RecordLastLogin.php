<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Escribe users.last_login_at (DATABASE.md §6.2). Por consulta directa para no
 * tocar updated_at: iniciar sesión no es modificar la cuenta.
 */
class RecordLastLogin
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            User::whereKey($event->user->getKey())->update(['last_login_at' => now()]);
        }
    }
}
