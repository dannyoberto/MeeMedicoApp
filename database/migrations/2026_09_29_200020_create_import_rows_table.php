<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §12.2
        DB::statement(<<<'SQL'
            CREATE TABLE import_rows (
                id          ulid PRIMARY KEY,
                batch_id    ulid NOT NULL REFERENCES import_batches(id) ON DELETE CASCADE,
                row_number  integer NOT NULL,

                -- la fila original, intacta
                raw_payload jsonb    NOT NULL,
                -- sha256 del payload crudo
                row_hash    char(64) NOT NULL,

                -- campos normalizados por el pipeline
                n_first_name    varchar(100) NULL,
                n_last_name     varchar(150) NULL,
                n_name_key      varchar(191) NULL,
                n_license       varchar(100) NULL,
                n_phone_e164    varchar(30)  NULL,
                n_email         varchar(255) NULL,
                n_country_id    ulid NULL REFERENCES countries(id) ON DELETE RESTRICT,
                n_city_id       ulid NULL REFERENCES cities(id)    ON DELETE RESTRICT,
                n_specialty_ids jsonb NULL,
                n_address       varchar(300) NULL,

                -- resultado del matching
                match_type varchar(20) NULL
                           CONSTRAINT import_rows_match_type_chk
                           CHECK (match_type IN
                               ('external_ref','license','phone','email','name_city','none')),
                match_confidence varchar(10) NOT NULL DEFAULT 'none'
                           CONSTRAINT import_rows_confidence_chk
                           CHECK (match_confidence IN ('strong','weak','none')),
                matched_doctor_id    ulid NULL REFERENCES doctors(id) ON DELETE SET NULL,
                candidate_doctor_ids jsonb NULL,

                status varchar(20) NOT NULL DEFAULT 'pending'
                       CONSTRAINT import_rows_status_chk
                       CHECK (status IN
                           ('pending','normalized','matched','new','needs_review',
                            'approved','applied','skipped','failed','suppressed')),

                validation_errors jsonb NULL,

                -- resolución humana
                resolution varchar(20) NULL
                           CONSTRAINT import_rows_resolution_chk
                           CHECK (resolution IN ('create_new','link_existing','discard')),
                resolved_by_user_id ulid NULL REFERENCES users(id) ON DELETE SET NULL,
                resolved_at         timestamptz NULL,

                applied_doctor_id ulid NULL REFERENCES doctors(id) ON DELETE SET NULL,
                applied_at        timestamptz NULL,

                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),

                CONSTRAINT import_rows_batch_hash_uniq UNIQUE (batch_id, row_hash)
            )
        SQL);

        DB::statement('CREATE INDEX import_rows_batch_status_idx ON import_rows (batch_id, status)');
        DB::statement("CREATE INDEX import_rows_review_idx ON import_rows (batch_id) WHERE status = 'needs_review'");
        DB::statement('CREATE INDEX import_rows_license_idx ON import_rows (n_country_id, n_license) WHERE n_license IS NOT NULL');
        DB::statement('CREATE INDEX import_rows_namekey_trgm_gin ON import_rows USING gin (n_name_key gin_trgm_ops)');
        DB::statement('CREATE INDEX import_rows_payload_gin ON import_rows USING gin (raw_payload)');
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
    }
};
