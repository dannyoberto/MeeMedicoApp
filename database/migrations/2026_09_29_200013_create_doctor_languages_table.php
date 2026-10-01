<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §9.4
        DB::statement(<<<'SQL'
            CREATE TABLE doctor_languages (
                doctor_id   ulid NOT NULL REFERENCES doctors(id)    ON DELETE CASCADE,
                language_id ulid NOT NULL REFERENCES languages(id)  ON DELETE RESTRICT,
                created_at  timestamptz NOT NULL DEFAULT now(),
                PRIMARY KEY (doctor_id, language_id)
            )
        SQL);

        DB::statement('CREATE INDEX doctor_languages_language_idx ON doctor_languages (language_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_languages');
    }
};
