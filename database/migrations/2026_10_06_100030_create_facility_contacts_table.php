<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §9.12. Mismo patrón que doctor_contacts. Que location_id sea una sede
        // del mismo establecimiento lo impone FacilityContactsAction, no el motor.
        DB::statement(<<<'SQL'
            CREATE TABLE facility_contacts (
                id               ulid PRIMARY KEY,
                facility_id      ulid NOT NULL REFERENCES facilities(id) ON DELETE CASCADE,
                location_id      ulid NULL     REFERENCES locations(id)  ON DELETE SET NULL,

                type             varchar(20) NOT NULL
                                 CONSTRAINT facility_contacts_type_chk
                                 CHECK (type IN ('phone','mobile','whatsapp','email','website')),

                value            varchar(255) NOT NULL,
                -- E.164 para teléfonos, minúsculas para email
                value_normalized varchar(255) NOT NULL,
                -- "Emergencias", "Citas", "Central"
                label            varchar(100) NULL,

                is_public        boolean NOT NULL DEFAULT true,
                is_primary       boolean NOT NULL DEFAULT false,

                source           varchar(20) NOT NULL DEFAULT 'admin'
                                 CONSTRAINT facility_contacts_source_chk
                                 CHECK (source IN ('import','admin')),

                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),

                CONSTRAINT facility_contacts_value_uniq UNIQUE (facility_id, type, value_normalized)
            )
        SQL);

        DB::statement('CREATE UNIQUE INDEX facility_contacts_one_primary_uniq ON facility_contacts (facility_id, type) WHERE is_primary');
        DB::statement('CREATE INDEX facility_contacts_location_idx ON facility_contacts (location_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_contacts');
    }
};
