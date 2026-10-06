<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * LISTA CANÓNICA de roles y permisos. Los documentos la referencian, no la copian.
 * Un permiso nuevo se añade aquí primero.
 *
 * Convención de nombres:
 * - plural (`profiles.update`): opera sobre cualquier registro; lo usa el backoffice.
 * - singular (`profile.update`): opera solo sobre lo propio; el alcance lo limita una
 *   Policy (doctors.user_id = auth()->id() y los campos de MODELO-IDENTIDAD.md §6).
 *
 * Es idempotente: cada ejecución deja los roles con exactamente estos permisos.
 */
class PermissionSeeder extends Seeder
{
    /**
     * Permisos del backoffice (DATABASE.md §16.2).
     */
    public const ADMIN_PERMISSIONS = [
        // entrar al panel de Filament (User::canAccessPanel)
        'backoffice.access',

        'doctors.view', 'doctors.create', 'doctors.update',
        'doctors.verify', 'doctors.publish', 'doctors.suspend', 'doctors.merge',

        'profiles.update',

        'specialties.view', 'specialties.create', 'specialties.update', 'specialties.delete',
        'locations.view', 'locations.create', 'locations.update', 'locations.delete',
        'geography.manage',

        'contacts.view', 'contacts.update',

        // establecimientos y seguros (MODULO-ESTABLECIMIENTOS-SEGUROS.md)
        'facilities.view', 'facilities.create', 'facilities.update', 'facilities.delete',
        'facilities.publish', 'networks.manage',
        'insurers.view', 'insurers.create', 'insurers.update', 'insurers.delete',

        'claims.view', 'claims.approve', 'claims.reject',

        'imports.view', 'imports.create', 'imports.resolve', 'imports.apply',
        'suppressions.manage',

        'users.view', 'users.create', 'users.update', 'users.suspend',
        'roles.manage',

        'activity.view',
    ];

    /**
     * Permisos del médico sobre su propia ficha.
     */
    public const DOCTOR_PERMISSIONS = [
        'profile.view',
        'profile.claim',
        'profile.update',
    ];

    public const ROLES = [
        'admin' => self::ADMIN_PERMISSIONS,
        'doctor' => self::DOCTOR_PERMISSIONS,
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([...self::ADMIN_PERMISSIONS, ...self::DOCTOR_PERMISSIONS] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // DatabaseSeeder usa WithoutModelEvents, que desactiva la invalidación automática
        // de la caché de spatie: sin esto, syncPermissions no ve los permisos recién creados.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
