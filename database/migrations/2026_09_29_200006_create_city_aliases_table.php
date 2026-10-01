<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §7.4
        DB::statement(<<<'SQL'
            CREATE TABLE city_aliases (
                id               ulid PRIMARY KEY,
                city_id          ulid NOT NULL REFERENCES cities(id) ON DELETE CASCADE,
                country_id       ulid NOT NULL REFERENCES countries(id) ON DELETE RESTRICT,

                -- tal como venía en la fuente
                alias            varchar(150) NOT NULL,
                -- minúsculas, sin acentos, escrito por la app
                alias_normalized varchar(150) NOT NULL,

                source           varchar(20) NOT NULL DEFAULT 'import'
                                 CONSTRAINT city_aliases_source_chk
                                 CHECK (source IN ('import','admin')),

                created_at       timestamptz NOT NULL DEFAULT now(),

                CONSTRAINT city_aliases_country_alias_uniq UNIQUE (country_id, alias_normalized)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('city_aliases');
    }
};
