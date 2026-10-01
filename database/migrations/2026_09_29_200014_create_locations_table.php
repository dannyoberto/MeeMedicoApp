<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §9.5. Sin índice sobre (latitude, longitude): PostGIS llega en Fase 3.
        DB::statement(<<<'SQL'
            CREATE TABLE locations (
                id                 ulid PRIMARY KEY,

                country_id         ulid NOT NULL,
                region_id          ulid NOT NULL,
                city_id            ulid NOT NULL,

                -- "Torre Médica Momentum, piso 4"
                name               varchar(200) NULL,
                address            varchar(255) NOT NULL,
                address_2          varchar(255) NULL,
                postal_code        varchar(30)  NULL,
                -- escrita por la app, para deduplicar
                address_normalized varchar(300) NOT NULL,

                latitude           numeric(10,8) NULL,
                longitude          numeric(11,8) NULL,

                status             varchar(20) NOT NULL DEFAULT 'active'
                                   CONSTRAINT locations_status_chk
                                   CHECK (status IN ('active','inactive')),

                created_by_user_id ulid NULL REFERENCES users(id) ON DELETE SET NULL,

                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),

                CONSTRAINT locations_city_region_fk
                    FOREIGN KEY (city_id, region_id)
                    REFERENCES cities (id, region_id) ON DELETE RESTRICT,

                CONSTRAINT locations_region_country_fk
                    FOREIGN KEY (region_id, country_id)
                    REFERENCES regions (id, country_id) ON DELETE RESTRICT,

                CONSTRAINT locations_coordinates_chk
                    CHECK ((latitude IS NULL) = (longitude IS NULL))
            )
        SQL);

        DB::statement('CREATE INDEX locations_city_idx ON locations (city_id)');
        DB::statement('CREATE INDEX locations_country_idx ON locations (country_id)');
        DB::statement('CREATE INDEX locations_address_norm_idx ON locations (city_id, address_normalized)');
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
