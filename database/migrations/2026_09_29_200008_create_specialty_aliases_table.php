<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §8.2
        DB::statement(<<<'SQL'
            CREATE TABLE specialty_aliases (
                id               ulid PRIMARY KEY,
                specialty_id     ulid NOT NULL REFERENCES specialties(id) ON DELETE CASCADE,

                alias            varchar(150) NOT NULL,
                alias_normalized varchar(150) NOT NULL,

                kind             varchar(20) NOT NULL DEFAULT 'import'
                                 CONSTRAINT specialty_aliases_kind_chk
                                 CHECK (kind IN ('import','seo','local')),

                created_at       timestamptz NOT NULL DEFAULT now(),

                CONSTRAINT specialty_aliases_alias_uniq UNIQUE (alias_normalized)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('specialty_aliases');
    }
};
