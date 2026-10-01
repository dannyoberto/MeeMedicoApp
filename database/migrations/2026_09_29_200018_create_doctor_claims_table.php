<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §10.1
        DB::statement(<<<'SQL'
            CREATE TABLE doctor_claims (
                id                     ulid PRIMARY KEY,
                doctor_id              ulid NOT NULL REFERENCES doctors(id) ON DELETE RESTRICT,
                user_id                ulid NOT NULL REFERENCES users(id)   ON DELETE RESTRICT,

                status                 varchar(20) NOT NULL DEFAULT 'pending'
                                       CONSTRAINT doctor_claims_status_chk
                                       CHECK (status IN ('pending','approved','rejected','cancelled')),

                claimed_license_number varchar(100) NULL,
                contact_email          varchar(255) NULL,
                contact_phone          varchar(30)  NULL,
                evidence_path          varchar(500) NULL,
                applicant_notes        text NULL,

                submitted_at           timestamptz NOT NULL DEFAULT now(),
                reviewed_by_user_id    ulid NULL REFERENCES users(id) ON DELETE SET NULL,
                reviewed_at            timestamptz NULL,
                resolution_reason      varchar(255) NULL,

                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),

                CONSTRAINT doctor_claims_resolved_requires_trace_chk
                    CHECK (status = 'pending' OR reviewed_at IS NOT NULL OR status = 'cancelled')
            )
        SQL);

        // un solo claim aprobado por médico, para siempre
        DB::statement("CREATE UNIQUE INDEX doctor_claims_one_approved_uniq ON doctor_claims (doctor_id) WHERE status = 'approved'");

        // un usuario no puede tener dos solicitudes abiertas sobre la misma ficha
        DB::statement("CREATE UNIQUE INDEX doctor_claims_one_pending_per_user_uniq ON doctor_claims (doctor_id, user_id) WHERE status = 'pending'");

        DB::statement("CREATE INDEX doctor_claims_queue_idx ON doctor_claims (submitted_at) WHERE status = 'pending'");
        DB::statement('CREATE INDEX doctor_claims_user_idx ON doctor_claims (user_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_claims');
    }
};
