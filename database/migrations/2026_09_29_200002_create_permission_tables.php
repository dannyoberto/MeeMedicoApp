<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas de spatie/laravel-permission con ULID y timestamptz (DATABASE.md §6.3).
 *
 * Sustituye al stub del paquete, que crea PK bigint. La estructura (tablas, PK,
 * índices, cascadas) es la del stub; solo cambian los tipos.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE permissions (
                id         ulid PRIMARY KEY,
                name       varchar(150) NOT NULL,
                guard_name varchar(50)  NOT NULL DEFAULT 'web',
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT permissions_name_guard_uniq UNIQUE (name, guard_name)
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE roles (
                id         ulid PRIMARY KEY,
                name       varchar(100) NOT NULL,
                guard_name varchar(50)  NOT NULL DEFAULT 'web',
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT roles_name_guard_uniq UNIQUE (name, guard_name)
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE role_has_permissions (
                permission_id ulid NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
                role_id       ulid NOT NULL REFERENCES roles(id)       ON DELETE CASCADE,
                PRIMARY KEY (permission_id, role_id)
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE model_has_roles (
                role_id    ulid         NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
                model_type varchar(255) NOT NULL,
                model_id   varchar(26)  NOT NULL,
                PRIMARY KEY (role_id, model_id, model_type)
            )
        SQL);

        DB::statement('CREATE INDEX model_has_roles_model_idx ON model_has_roles (model_id, model_type)');

        DB::statement(<<<'SQL'
            CREATE TABLE model_has_permissions (
                permission_id ulid         NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
                model_type    varchar(255) NOT NULL,
                model_id      varchar(26)  NOT NULL,
                PRIMARY KEY (permission_id, model_id, model_type)
            )
        SQL);

        DB::statement('CREATE INDEX model_has_permissions_model_idx ON model_has_permissions (model_id, model_type)');

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
