<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §9.14. Que la aseguradora sea del país del médico lo impone DoctorInsurersAction.
        DB::statement(<<<'SQL'
            CREATE TABLE doctor_insurers (
                doctor_id  ulid NOT NULL REFERENCES doctors(id)  ON DELETE CASCADE,
                insurer_id ulid NOT NULL REFERENCES insurers(id) ON DELETE RESTRICT,
                created_at timestamptz NOT NULL DEFAULT now(),
                PRIMARY KEY (doctor_id, insurer_id)
            )
        SQL);

        DB::statement('CREATE INDEX doctor_insurers_insurer_idx ON doctor_insurers (insurer_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_insurers');
    }
};
