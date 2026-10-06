<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §9.14: convenios del establecimiento, independientes de los de sus médicos.
        DB::statement(<<<'SQL'
            CREATE TABLE facility_insurers (
                facility_id ulid NOT NULL REFERENCES facilities(id) ON DELETE CASCADE,
                insurer_id  ulid NOT NULL REFERENCES insurers(id)   ON DELETE RESTRICT,
                created_at  timestamptz NOT NULL DEFAULT now(),
                PRIMARY KEY (facility_id, insurer_id)
            )
        SQL);

        DB::statement('CREATE INDEX facility_insurers_insurer_idx ON facility_insurers (insurer_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_insurers');
    }
};
