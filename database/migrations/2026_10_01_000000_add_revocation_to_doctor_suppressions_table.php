<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §14.1: la supresión no se borra ni se edita; se revoca una sola vez.
        DB::statement(<<<'SQL'
            ALTER TABLE doctor_suppressions
                ADD COLUMN revocation_requested_at timestamptz  NULL,
                ADD COLUMN revocation_reason       varchar(255) NULL,
                ADD COLUMN revoked_by_user_id      ulid NULL REFERENCES users(id) ON DELETE SET NULL,
                ADD COLUMN revoked_at              timestamptz  NULL,
                ADD CONSTRAINT doctor_suppressions_revocation_trace_chk CHECK (
                        (revoked_at IS NULL) = (revocation_reason IS NULL)
                    AND (revoked_at IS NULL) = (revocation_requested_at IS NULL))
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE doctor_suppressions
                DROP CONSTRAINT doctor_suppressions_revocation_trace_chk,
                DROP COLUMN revoked_at,
                DROP COLUMN revoked_by_user_id,
                DROP COLUMN revocation_reason,
                DROP COLUMN revocation_requested_at
        SQL);
    }
};
