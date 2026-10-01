<?php

namespace App\Domain\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Alta de una cuenta del equipo desde el backoffice. La persona define su propia
 * contraseña con el enlace: el admin nunca la conoce.
 */
class InviteUserAction
{
    public function __construct(private readonly SendPasswordSetupLinkAction $sendLink) {}

    /**
     * @param  array<int, string>  $roles  nombres de rol
     *
     * @throws ValidationException (clave: email)
     */
    public function execute(string $name, string $email, array $roles, ?User $actor): User
    {
        $email = mb_strtolower(trim($email));

        if (User::whereRaw('lower(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => 'Ya existe una cuenta con ese correo.']);
        }

        $user = DB::transaction(function () use ($name, $email, $roles, $actor) {
            // users.password es NOT NULL: una contraseña aleatoria que nadie conoce
            // mantiene la cuenta cerrada hasta que se use el enlace.
            $user = User::create(['name' => $name, 'email' => $email, 'password' => Str::random(64)]);

            foreach ($roles as $role) {
                $user->assignRole($role);

                activity()
                    ->performedOn($user)
                    ->causedBy($actor)
                    ->event('role.assigned')
                    ->withProperties(['role' => $role, 'via' => 'invitation'])
                    ->log('role.assigned');
            }

            return $user;
        });

        // Fuera de la transacción: el correo no debe salir si el alta se revierte.
        $this->sendLink->execute($user);

        return $user;
    }
}
