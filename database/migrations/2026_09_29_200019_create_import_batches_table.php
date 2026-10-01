<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §12.1
        DB::statement(<<<'SQL'
            CREATE TABLE import_batches (
                id                 ulid PRIMARY KEY,
                country_id         ulid NOT NULL REFERENCES countries(id) ON DELETE RESTRICT,
                created_by_user_id ulid NULL REFERENCES users(id) ON DELETE SET NULL,

                -- 'colegio_medicos_cr'
                source             varchar(50)  NOT NULL,
                file_name          varchar(255) NOT NULL,
                -- sha256 del archivo
                file_hash          char(64)     NOT NULL,
                file_path          varchar(500) NULL,

                status             varchar(20) NOT NULL DEFAULT 'ingesting'
                                   CONSTRAINT import_batches_status_chk
                                   CHECK (status IN
                                       ('ingesting','normalizing','matching','review','applying','completed','failed')),

                rows_total    integer NOT NULL DEFAULT 0,
                rows_new      integer NOT NULL DEFAULT 0,
                rows_matched  integer NOT NULL DEFAULT 0,
                rows_review   integer NOT NULL DEFAULT 0,
                rows_applied  integer NOT NULL DEFAULT 0,
                rows_skipped  integer NOT NULL DEFAULT 0,
                rows_failed   integer NOT NULL DEFAULT 0,

                started_at  timestamptz NULL,
                finished_at timestamptz NULL,
                notes       text NULL,

                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),

                CONSTRAINT import_batches_file_hash_uniq UNIQUE (source, file_hash)
            )
        SQL);

        // FK diferida de doctors, ahora que existe la tabla destino (§15)
        DB::statement(<<<'SQL'
            ALTER TABLE doctors
                ADD CONSTRAINT doctors_import_batch_fk
                FOREIGN KEY (import_batch_id) REFERENCES import_batches(id) ON DELETE SET NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE doctors DROP CONSTRAINT IF EXISTS doctors_import_batch_fk');
        Schema::dropIfExists('import_batches');
    }
};
