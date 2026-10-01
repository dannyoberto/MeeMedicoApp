<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §9.3
        DB::statement(<<<'SQL'
            CREATE TABLE doctor_specialties (
                doctor_id    ulid NOT NULL REFERENCES doctors(id)     ON DELETE CASCADE,
                specialty_id ulid NOT NULL REFERENCES specialties(id) ON DELETE RESTRICT,
                is_primary   boolean NOT NULL DEFAULT false,
                created_at   timestamptz NOT NULL DEFAULT now(),
                PRIMARY KEY (doctor_id, specialty_id)
            )
        SQL);

        DB::statement('CREATE INDEX doctor_specialties_specialty_idx ON doctor_specialties (specialty_id)');

        // máximo una especialidad primaria por médico, garantizado por el motor
        DB::statement('CREATE UNIQUE INDEX doctor_specialties_one_primary_uniq ON doctor_specialties (doctor_id) WHERE is_primary');
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_specialties');
    }
};
