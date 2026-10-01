<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §7.1
        DB::statement(<<<'SQL'
            CREATE TABLE countries (
                id         ulid PRIMARY KEY,
                name       varchar(150) NOT NULL,
                code       char(2)      NOT NULL,
                dial_code  varchar(5)   NOT NULL,
                slug       varchar(180) NOT NULL,

                status     varchar(20) NOT NULL DEFAULT 'inactive'
                           CONSTRAINT countries_status_chk
                           CHECK (status IN ('active','inactive')),

                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),

                CONSTRAINT countries_code_uniq UNIQUE (code),
                CONSTRAINT countries_slug_uniq UNIQUE (slug)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
