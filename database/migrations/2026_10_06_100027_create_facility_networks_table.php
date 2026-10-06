<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §9.9. Red operadora (CCSS, IGSS, grupo privado). Sin slug: no hay página de red.
        DB::statement(<<<'SQL'
            CREATE TABLE facility_networks (
                id         ulid PRIMARY KEY,
                country_id ulid NOT NULL REFERENCES countries(id) ON DELETE RESTRICT,

                -- 'Caja Costarricense de Seguro Social'
                name       varchar(200) NOT NULL,
                -- 'CCSS'
                short_name varchar(30)  NULL,

                sector     varchar(20) NOT NULL
                           CONSTRAINT facility_networks_sector_chk
                           CHECK (sector IN ('public','private','mixed')),

                status     varchar(20) NOT NULL DEFAULT 'active'
                           CONSTRAINT facility_networks_status_chk
                           CHECK (status IN ('active','inactive')),

                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),

                CONSTRAINT facility_networks_country_name_uniq UNIQUE (country_id, name),
                -- destino de FK compuesta desde facilities
                CONSTRAINT facility_networks_id_country_uniq   UNIQUE (id, country_id)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_networks');
    }
};
