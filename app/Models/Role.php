<?php

namespace App\Models;

use App\Models\Concerns\HasUppercaseUlids;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasUppercaseUlids;

    /**
     * Nombre legible de cada rol de PermissionSeeder::ROLES. El nombre técnico
     * ("admin") es el que usan las Policies; este es el que ve el operador.
     */
    public const LABELS = [
        'admin' => 'Administrador',
        'doctor' => 'Médico',
    ];

    public static function labelFor(string $name): string
    {
        return self::LABELS[$name] ?? $name;
    }
}
