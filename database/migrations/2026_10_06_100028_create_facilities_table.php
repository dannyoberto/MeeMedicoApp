<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §9.10. Sin name_normalized ni merged: llegan con la importación.
        DB::statement(<<<'SQL'
            CREATE TABLE facilities (
                id                 ulid PRIMARY KEY,
                country_id         ulid NOT NULL REFERENCES countries(id) ON DELETE RESTRICT,
                network_id         ulid NULL,

                -- 'Hospital Clínica Bíblica'
                name               varchar(200) NOT NULL,
                slug               varchar(220) NOT NULL,

                type               varchar(20) NOT NULL
                                   CONSTRAINT facilities_type_chk
                                   CHECK (type IN ('hospital','clinic','medical_center','health_center')),

                sector             varchar(20) NOT NULL
                                   CONSTRAINT facilities_sector_chk
                                   CHECK (sector IN ('public','private','mixed')),

                -- contenido de la futura landing
                description        text NULL,
                logo_path          varchar(500) NULL,

                status             varchar(20) NOT NULL DEFAULT 'draft'
                                   CONSTRAINT facilities_status_chk
                                   CHECK (status IN ('draft','active','inactive')),

                created_by_user_id ulid NULL REFERENCES users(id) ON DELETE SET NULL,

                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),

                -- URL futura: /{pais}/clinicas/{slug} para todos los tipos
                CONSTRAINT facilities_country_slug_uniq UNIQUE (country_id, slug),
                -- destino de FK compuesta desde locations
                CONSTRAINT facilities_id_country_uniq   UNIQUE (id, country_id),

                -- la red, si la hay, es del mismo país
                CONSTRAINT facilities_network_country_fk
                    FOREIGN KEY (network_id, country_id)
                    REFERENCES facility_networks (id, country_id) ON DELETE RESTRICT
            )
        SQL);

        DB::statement('CREATE INDEX facilities_country_type_idx ON facilities (country_id, type)');
        DB::statement('CREATE INDEX facilities_network_idx ON facilities (network_id) WHERE network_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};
