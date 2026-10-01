<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §14.3. Única tabla sin ULID: append-only y de alto volumen.
        DB::statement(<<<'SQL'
            CREATE TABLE doctor_contact_events (
                id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                doctor_id    ulid NOT NULL REFERENCES doctors(id) ON DELETE CASCADE,
                country_id   ulid NOT NULL REFERENCES countries(id) ON DELETE RESTRICT,

                contact_type varchar(20) NOT NULL
                             CONSTRAINT contact_events_type_chk
                             CHECK (contact_type IN ('phone','whatsapp','email','website','directions')),

                source       varchar(20) NOT NULL DEFAULT 'profile'
                             CONSTRAINT contact_events_source_chk
                             CHECK (source IN ('profile','listing','map')),

                -- hash con sal, sin PII
                session_hash char(64) NULL,
                occurred_at  timestamptz NOT NULL DEFAULT now()
            )
        SQL);

        DB::statement('CREATE INDEX contact_events_doctor_time_idx ON doctor_contact_events (doctor_id, occurred_at DESC)');
        DB::statement('CREATE INDEX contact_events_time_idx ON doctor_contact_events (occurred_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_contact_events');
    }
};
