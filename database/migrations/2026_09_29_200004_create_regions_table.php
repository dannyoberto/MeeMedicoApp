<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §7.2
        DB::statement(<<<'SQL'
            CREATE TABLE regions (
                id         ulid PRIMARY KEY,
                country_id ulid NOT NULL REFERENCES countries(id) ON DELETE RESTRICT,
                name       varchar(150) NOT NULL,
                slug       varchar(180) NOT NULL,

                status     varchar(20) NOT NULL DEFAULT 'active'
                           CONSTRAINT regions_status_chk
                           CHECK (status IN ('active','inactive')),

                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),

                CONSTRAINT regions_country_slug_uniq UNIQUE (country_id, slug),
                -- destino de FK compuesta
                CONSTRAINT regions_id_country_uniq   UNIQUE (id, country_id)
            )
        SQL);

        DB::statement('CREATE INDEX regions_country_idx ON regions (country_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
