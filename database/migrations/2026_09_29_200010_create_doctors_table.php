<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §9.1
        DB::statement(<<<'SQL'
            CREATE TABLE doctors (
                id                   ulid PRIMARY KEY,

                country_id           ulid NOT NULL REFERENCES countries(id) ON DELETE RESTRICT,
                user_id              ulid NULL     REFERENCES users(id)     ON DELETE RESTRICT,
                created_by_user_id   ulid NULL     REFERENCES users(id)     ON DELETE SET NULL,

                first_name           varchar(100) NOT NULL,
                last_name            varchar(150) NOT NULL,
                professional_name    varchar(200) NULL,

                slug                 varchar(220) NOT NULL,
                -- escrita por la app, base del matching
                name_normalized      varchar(191) NOT NULL,

                gender               varchar(20) NULL
                                     CONSTRAINT doctors_gender_chk
                                     CHECK (gender IN ('male','female','other','undisclosed')),

                -- licencia profesional
                license_number       varchar(100) NULL,
                license_source       varchar(30) NULL
                                     CONSTRAINT doctors_license_source_chk
                                     CHECK (license_source IN
                                         ('official_registry','import_third_party','self_declared','admin','claim')),
                license_verified_at  timestamptz NULL,

                -- publicación
                status               varchar(20) NOT NULL DEFAULT 'draft'
                                     CONSTRAINT doctors_status_chk
                                     CHECK (status IN ('draft','active','inactive','suspended','merged')),
                published_at         timestamptz NULL,

                -- verificación profesional
                verification_status  varchar(20) NOT NULL DEFAULT 'unverified'
                                     CONSTRAINT doctors_verification_status_chk
                                     CHECK (verification_status IN ('unverified','pending','verified','rejected')),
                verification_source  varchar(30) NULL
                                     CONSTRAINT doctors_verification_source_chk
                                     CHECK (verification_source IN ('official_registry','document','manual','claim')),
                verified_at          timestamptz NULL,
                verified_by_user_id  ulid NULL REFERENCES users(id) ON DELETE SET NULL,

                -- reclamación
                claim_status         varchar(20) NOT NULL DEFAULT 'unclaimed'
                                     CONSTRAINT doctors_claim_status_chk
                                     CHECK (claim_status IN ('unclaimed','pending','claimed','rejected')),
                claimed_at           timestamptz NULL,

                -- procedencia y fusión
                source               varchar(20) NOT NULL DEFAULT 'admin'
                                     CONSTRAINT doctors_source_chk
                                     CHECK (source IN ('import','admin','claim')),
                -- FK añadida en la migración de import_batches (§12.1)
                import_batch_id      ulid NULL,
                merged_into_doctor_id ulid NULL REFERENCES doctors(id) ON DELETE RESTRICT,

                -- búsqueda
                search_vector tsvector GENERATED ALWAYS AS (
                    to_tsvector('es_unaccent',
                        coalesce(first_name,'') || ' ' ||
                        coalesce(last_name,'')  || ' ' ||
                        coalesce(professional_name,'')
                    )
                ) STORED,

                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),

                -- invariantes de estado
                CONSTRAINT doctors_merged_requires_target_chk
                    CHECK (status <> 'merged' OR merged_into_doctor_id IS NOT NULL),
                CONSTRAINT doctors_not_merged_into_self_chk
                    CHECK (merged_into_doctor_id IS NULL OR merged_into_doctor_id <> id),
                CONSTRAINT doctors_claimed_requires_user_chk
                    CHECK (claim_status <> 'claimed' OR user_id IS NOT NULL),
                CONSTRAINT doctors_verified_requires_trace_chk
                    CHECK (verification_status <> 'verified'
                           OR (verified_at IS NOT NULL AND verification_source IS NOT NULL)),
                CONSTRAINT doctors_published_requires_active_chk
                    CHECK (published_at IS NULL OR status <> 'draft')
            )
        SQL);

        // un usuario administra como máximo un médico
        DB::statement('CREATE UNIQUE INDEX doctors_user_uniq ON doctors (user_id) WHERE user_id IS NOT NULL');

        // URL pública: /{pais}/medicos/{slug}
        DB::statement('CREATE UNIQUE INDEX doctors_country_slug_uniq ON doctors (country_id, slug)');

        // clave natural fuerte cuando existe licencia
        DB::statement('CREATE UNIQUE INDEX doctors_country_license_uniq ON doctors (country_id, license_number) WHERE license_number IS NOT NULL');

        // listados públicos: solo fichas publicadas
        DB::statement("CREATE INDEX doctors_public_idx ON doctors (country_id, last_name, first_name) WHERE status = 'active'");

        // matching difuso de nombres en la importación
        DB::statement('CREATE INDEX doctors_name_trgm_gin ON doctors USING gin (name_normalized gin_trgm_ops)');

        // búsqueda textual
        DB::statement('CREATE INDEX doctors_search_gin ON doctors USING gin (search_vector)');

        // colas de trabajo del backoffice
        DB::statement("CREATE INDEX doctors_claim_pending_idx ON doctors (claim_status) WHERE claim_status = 'pending'");
        DB::statement("CREATE INDEX doctors_verification_pending_idx ON doctors (verification_status) WHERE verification_status = 'pending'");

        DB::statement('CREATE INDEX doctors_created_by_idx ON doctors (created_by_user_id)');
        DB::statement('CREATE INDEX doctors_merged_into_idx ON doctors (merged_into_doctor_id) WHERE merged_into_doctor_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};
