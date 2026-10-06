<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §9.13. Por país y sin planes.
        DB::statement(<<<'SQL'
            CREATE TABLE insurers (
                id         ulid PRIMARY KEY,
                country_id ulid NOT NULL REFERENCES countries(id) ON DELETE RESTRICT,

                -- 'BMI Seguros', 'SeNaSa'
                name       varchar(200) NOT NULL,
                slug       varchar(220) NOT NULL,

                type       varchar(20) NOT NULL
                           CONSTRAINT insurers_type_chk
                           CHECK (type IN ('private','public')),

                status     varchar(20) NOT NULL DEFAULT 'active'
                           CONSTRAINT insurers_status_chk
                           CHECK (status IN ('active','inactive')),

                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),

                CONSTRAINT insurers_country_slug_uniq UNIQUE (country_id, slug)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('insurers');
    }
};
