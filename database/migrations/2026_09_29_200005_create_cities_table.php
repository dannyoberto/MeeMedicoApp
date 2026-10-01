<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §7.3
        DB::statement(<<<'SQL'
            CREATE TABLE cities (
                id         ulid PRIMARY KEY,
                -- denormalizado: la URL lo exige
                country_id ulid NOT NULL,
                region_id  ulid NOT NULL,
                name       varchar(150) NOT NULL,
                slug       varchar(180) NOT NULL,

                status     varchar(20) NOT NULL DEFAULT 'active'
                           CONSTRAINT cities_status_chk
                           CHECK (status IN ('active','inactive')),

                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),

                -- el slug de ciudad viaja en la URL dentro del ámbito país
                CONSTRAINT cities_country_slug_uniq UNIQUE (country_id, slug),
                CONSTRAINT cities_id_region_uniq    UNIQUE (id, region_id),
                CONSTRAINT cities_id_country_uniq   UNIQUE (id, country_id),

                -- garantiza a nivel de motor que la región de la ciudad pertenece a su país
                CONSTRAINT cities_region_country_fk
                    FOREIGN KEY (region_id, country_id)
                    REFERENCES regions (id, country_id) ON DELETE RESTRICT
            )
        SQL);

        DB::statement('CREATE INDEX cities_region_idx ON cities (region_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
