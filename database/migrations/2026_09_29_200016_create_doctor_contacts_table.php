<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §9.7
        DB::statement(<<<'SQL'
            CREATE TABLE doctor_contacts (
                id               ulid PRIMARY KEY,
                doctor_id        ulid NOT NULL REFERENCES doctors(id)   ON DELETE CASCADE,
                location_id      ulid NULL     REFERENCES locations(id) ON DELETE SET NULL,

                type             varchar(20) NOT NULL
                                 CONSTRAINT doctor_contacts_type_chk
                                 CHECK (type IN ('phone','mobile','whatsapp','email','website')),

                -- tal como se muestra
                value            varchar(255) NOT NULL,
                -- E.164 para teléfonos, minúsculas para email
                value_normalized varchar(255) NOT NULL,
                -- "Consultorio", "Emergencias"
                label            varchar(100) NULL,

                is_public        boolean NOT NULL DEFAULT true,
                is_primary       boolean NOT NULL DEFAULT false,
                verified_at      timestamptz NULL,

                source           varchar(20) NOT NULL DEFAULT 'admin'
                                 CONSTRAINT doctor_contacts_source_chk
                                 CHECK (source IN ('import','admin','doctor')),

                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),

                CONSTRAINT doctor_contacts_value_uniq UNIQUE (doctor_id, type, value_normalized)
            )
        SQL);

        DB::statement('CREATE UNIQUE INDEX doctor_contacts_one_primary_uniq ON doctor_contacts (doctor_id, type) WHERE is_primary');
        DB::statement('CREATE INDEX doctor_contacts_normalized_idx ON doctor_contacts (value_normalized)');
        DB::statement('CREATE INDEX doctor_contacts_location_idx ON doctor_contacts (location_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_contacts');
    }
};
