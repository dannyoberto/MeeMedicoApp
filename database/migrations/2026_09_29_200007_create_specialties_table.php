<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §8.1
        DB::statement(<<<'SQL'
            CREATE TABLE specialties (
                id          ulid PRIMARY KEY,
                parent_id   ulid NULL REFERENCES specialties(id) ON DELETE RESTRICT,

                name        varchar(150) NOT NULL,
                slug        varchar(180) NOT NULL,
                description text NULL,

                status      varchar(20) NOT NULL DEFAULT 'active'
                            CONSTRAINT specialties_status_chk
                            CHECK (status IN ('active','inactive')),

                created_at  timestamptz NOT NULL DEFAULT now(),
                updated_at  timestamptz NOT NULL DEFAULT now(),

                CONSTRAINT specialties_slug_uniq UNIQUE (slug),
                CONSTRAINT specialties_no_self_parent_chk CHECK (parent_id IS NULL OR parent_id <> id)
            )
        SQL);

        DB::statement('CREATE INDEX specialties_parent_idx ON specialties (parent_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('specialties');
    }
};
