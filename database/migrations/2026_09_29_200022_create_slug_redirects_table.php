<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §14.2
        DB::statement(<<<'SQL'
            CREATE TABLE slug_redirects (
                id          ulid PRIMARY KEY,
                entity_type varchar(30) NOT NULL
                            CONSTRAINT slug_redirects_entity_chk
                            CHECK (entity_type IN ('doctor','specialty','city','region')),
                entity_id   ulid NOT NULL,
                country_id  ulid NULL REFERENCES countries(id) ON DELETE RESTRICT,

                old_slug    varchar(220) NOT NULL,
                created_at  timestamptz NOT NULL DEFAULT now(),

                -- NULLS NOT DISTINCT: country_id es NULL para specialty y, sin esto,
                -- dos redirecciones del mismo slug de especialidad no chocarían
                CONSTRAINT slug_redirects_uniq UNIQUE NULLS NOT DISTINCT (entity_type, country_id, old_slug)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('slug_redirects');
    }
};
