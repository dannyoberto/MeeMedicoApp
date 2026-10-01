<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §9.8
        DB::statement(<<<'SQL'
            CREATE TABLE doctor_external_references (
                id            ulid PRIMARY KEY,
                doctor_id     ulid NOT NULL REFERENCES doctors(id) ON DELETE CASCADE,

                -- 'colegio_medicos_cr', 'padron_gt'
                source        varchar(50)  NOT NULL,
                -- identificador en la fuente
                reference     varchar(100) NOT NULL,

                first_seen_at timestamptz NOT NULL,
                last_seen_at  timestamptz NOT NULL,
                created_at    timestamptz NOT NULL DEFAULT now(),

                CONSTRAINT doctor_external_refs_uniq UNIQUE (source, reference)
            )
        SQL);

        DB::statement('CREATE INDEX doctor_external_refs_doctor_idx ON doctor_external_references (doctor_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_external_references');
    }
};
