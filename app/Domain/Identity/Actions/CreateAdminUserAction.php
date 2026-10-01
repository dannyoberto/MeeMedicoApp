<?php

namespace App\Domain\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Crea una cuenta con el rol admin. La usa `user:create-admin`; el día que el
 * backoffice gestione usuarios, llamará a esta misma Action.
 */
class CreateAdminUserAction
{
    public function execute(string $name, string $email, string $password): User
    {
        $email = mb_strtolower(trim($email));

        if (User::whereRaw('lower(email) = ?', [$email])->exists()) {
            throw new InvalidArgumentException("Ya existe una cuenta con el correo {$email}.");
        }

        return DB::transaction(function () use ($name, $email, $password) {
            $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
            // Lo crea un operador con acceso a la consola: el correo se da por verificado.
            $user->forceFill(['email_verified_at' => now()])->save();

            $user->assignRole('admin');

            activity()
                ->performedOn($user)
                ->event('role.assigned')
                ->withProperties(['role' => 'admin', 'via' => 'console'])
                ->log('role.assigned');

            return $user;
        });
    }
}
