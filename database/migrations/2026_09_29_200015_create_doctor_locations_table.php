<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §9.6
        DB::statement(<<<'SQL'
            CREATE TABLE doctor_locations (
                doctor_id     ulid NOT NULL REFERENCES doctors(id)   ON DELETE CASCADE,
                location_id   ulid NOT NULL REFERENCES locations(id) ON DELETE RESTRICT,

                location_type varchar(20) NOT NULL DEFAULT 'office'
                              CONSTRAINT doctor_locations_type_chk
                              CHECK (location_type IN ('office','clinic','hospital','other')),

                is_primary    boolean NOT NULL DEFAULT false,
                created_at    timestamptz NOT NULL DEFAULT now(),

                PRIMARY KEY (doctor_id, location_id)
            )
        SQL);

        DB::statement('CREATE INDEX doctor_locations_location_idx ON doctor_locations (location_id)');
        DB::statement('CREATE UNIQUE INDEX doctor_locations_one_primary_uniq ON doctor_locations (doctor_id) WHERE is_primary');
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_locations');
    }
};
