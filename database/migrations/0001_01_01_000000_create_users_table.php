<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // DATABASE.md §6.2
        DB::statement(<<<'SQL'
            CREATE TABLE users (
                id                ulid PRIMARY KEY,
                name              varchar(150) NOT NULL,
                email             varchar(255) NOT NULL,
                password          varchar(255) NOT NULL,

                status            varchar(20) NOT NULL DEFAULT 'active'
                                  CONSTRAINT users_status_chk
                                  CHECK (status IN ('active','inactive','suspended')),

                email_verified_at timestamptz NULL,
                last_login_at     timestamptz NULL,
                remember_token    varchar(100) NULL,

                created_at        timestamptz NOT NULL DEFAULT now(),
                updated_at        timestamptz NOT NULL DEFAULT now()
            )
        SQL);

        DB::statement('CREATE UNIQUE INDEX users_email_uniq ON users (lower(email))');
        DB::statement("CREATE INDEX users_status_idx ON users (status) WHERE status <> 'active'");

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestampTz('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->rawColumn('user_id', 'ulid')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
