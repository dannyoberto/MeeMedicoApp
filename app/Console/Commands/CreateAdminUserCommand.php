<?php

namespace App\Console\Commands;

use App\Domain\Identity\Actions\CreateAdminUserAction;
use Illuminate\Console\Command;
use InvalidArgumentException;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdminUserCommand extends Command
{
    /**
     * La contraseña solo se pide de forma interactiva: como opción quedaría en el historial de la shell.
     */
    protected $signature = 'user:create-admin
                            {--name= : Nombre visible}
                            {--email= : Correo de acceso}';

    protected $description = 'Crea un usuario con el rol admin para el backoffice';

    public function handle(CreateAdminUserAction $createAdmin): int
    {
        $name = $this->option('name') ?: text('Nombre', required: true, validate: ['max:150']);
        $email = $this->option('email') ?: text('Correo', required: true, validate: ['email', 'max:255']);
        $password = password('Contraseña (mínimo 12 caracteres)', required: true, validate: ['min:12']);

        try {
            $user = $createAdmin->execute($name, $email, $password);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Admin creado: {$user->email} ({$user->id})");
        $this->line('Panel: '.$this->panelUrl());

        return self::SUCCESS;
    }

    private function panelUrl(): string
    {
        $appUrl = parse_url(config('app.url'));
        $port = isset($appUrl['port']) ? ':'.$appUrl['port'] : '';
        $domain = config('app.admin_domain');

        return $domain
            ? "{$appUrl['scheme']}://{$domain}{$port}"
            : rtrim(config('app.url'), '/').'/admin';
    }
}
