<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §8.3
        DB::statement(<<<'SQL'
            CREATE TABLE languages (
                id         ulid PRIMARY KEY,
                -- ISO 639-1: es, en, fr, pt
                code       varchar(5)  NOT NULL,
                name       varchar(100) NOT NULL,

                status     varchar(20) NOT NULL DEFAULT 'active'
                           CONSTRAINT languages_status_chk
                           CHECK (status IN ('active','inactive')),

                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),

                CONSTRAINT languages_code_uniq UNIQUE (code)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
