<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §14.1
        DB::statement(<<<'SQL'
            CREATE TABLE doctor_suppressions (
                id         ulid PRIMARY KEY,
                country_id ulid NOT NULL REFERENCES countries(id) ON DELETE RESTRICT,

                license_number   varchar(100) NULL,
                email_normalized varchar(255) NULL,
                phone_normalized varchar(30)  NULL,
                name_normalized  varchar(191) NULL,

                reason       varchar(255) NULL,
                requested_at timestamptz NOT NULL,

                created_by_user_id ulid NULL REFERENCES users(id) ON DELETE SET NULL,
                created_at timestamptz NOT NULL DEFAULT now(),

                CONSTRAINT doctor_suppressions_has_key_chk
                    CHECK (license_number IS NOT NULL
                        OR email_normalized IS NOT NULL
                        OR phone_normalized IS NOT NULL
                        OR name_normalized  IS NOT NULL)
            )
        SQL);

        DB::statement('CREATE INDEX doctor_suppressions_license_idx ON doctor_suppressions (country_id, license_number)');
        DB::statement('CREATE INDEX doctor_suppressions_phone_idx ON doctor_suppressions (phone_normalized)');
        DB::statement('CREATE INDEX doctor_suppressions_name_idx ON doctor_suppressions (country_id, name_normalized)');
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_suppressions');
    }
};
