<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Primitivos de PostgreSQL que el resto del esquema necesita (DATABASE.md §3, §4, §15).
 *
 * Corre antes que cualquier otra migración: todos los PK usan el dominio `ulid`
 * y `doctors.search_vector` referencia `es_unaccent`.
 *
 * Es idempotente porque `migrate:fresh` solo borra tablas: el dominio, la
 * configuración de búsqueda, la función y las extensiones sobreviven.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');

        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_type t
                    JOIN pg_namespace n ON n.oid = t.typnamespace
                    WHERE t.typname = 'ulid' AND n.nspname = 'public'
                ) THEN
                    CREATE DOMAIN ulid AS varchar(26)
                        COLLATE "C"
                        CHECK (VALUE ~ '^[0-7][0-9A-HJKMNP-TV-Z]{25}$');
                END IF;
            END
            $$
        SQL);

        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_ts_config WHERE cfgname = 'es_unaccent') THEN
                    CREATE TEXT SEARCH CONFIGURATION es_unaccent (COPY = spanish);
                END IF;
            END
            $$
        SQL);

        DB::statement(<<<'SQL'
            ALTER TEXT SEARCH CONFIGURATION es_unaccent
                ALTER MAPPING FOR hword, hword_part, word
                WITH unaccent, spanish_stem
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION immutable_unaccent(text)
            RETURNS text AS $$
                SELECT public.unaccent('public.unaccent', $1)
            $$ LANGUAGE sql IMMUTABLE PARALLEL SAFE STRICT
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS immutable_unaccent(text)');
        DB::statement('DROP TEXT SEARCH CONFIGURATION IF EXISTS es_unaccent');
        DB::statement('DROP DOMAIN IF EXISTS ulid');
        DB::statement('DROP EXTENSION IF EXISTS unaccent');
        DB::statement('DROP EXTENSION IF EXISTS pg_trgm');
    }
};
