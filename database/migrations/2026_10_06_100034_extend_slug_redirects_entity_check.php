<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // DATABASE.md §14.2: establecimientos y aseguradoras tienen slug, y ningún slug cambia sin 301.
        DB::statement(<<<'SQL'
            ALTER TABLE slug_redirects
                DROP CONSTRAINT slug_redirects_entity_chk,
                ADD CONSTRAINT slug_redirects_entity_chk
                    CHECK (entity_type IN ('doctor','specialty','city','region','facility','insurer'))
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE slug_redirects
                DROP CONSTRAINT slug_redirects_entity_chk,
                ADD CONSTRAINT slug_redirects_entity_chk
                    CHECK (entity_type IN ('doctor','specialty','city','region'))
        SQL);
    }
};
