<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §9.2
        DB::statement(<<<'SQL'
            CREATE TABLE doctor_profiles (
                id                 ulid PRIMARY KEY,
                doctor_id          ulid NOT NULL REFERENCES doctors(id) ON DELETE CASCADE,

                headline           varchar(255) NULL,
                bio                text NULL,
                education          text NULL,
                experience         text NULL,
                profile_photo_path varchar(500) NULL,

                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),

                CONSTRAINT doctor_profiles_doctor_uniq UNIQUE (doctor_id)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_profiles');
    }
};
