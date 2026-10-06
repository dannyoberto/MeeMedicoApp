<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §9.11: el establecimiento es dueño de sus sedes. Columna nullable,
        // así que la FK no tiene nada que validar en las ubicaciones existentes.
        DB::statement(<<<'SQL'
            ALTER TABLE locations
                ADD COLUMN facility_id ulid NULL,
                -- la sede está en el mismo país que su establecimiento
                ADD CONSTRAINT locations_facility_country_fk
                    FOREIGN KEY (facility_id, country_id)
                    REFERENCES facilities (id, country_id) ON DELETE RESTRICT
        SQL);

        DB::statement('CREATE INDEX locations_facility_idx ON locations (facility_id) WHERE facility_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX locations_facility_idx');
        DB::statement(<<<'SQL'
            ALTER TABLE locations
                DROP CONSTRAINT locations_facility_country_fk,
                DROP COLUMN facility_id
        SQL);
    }
};
