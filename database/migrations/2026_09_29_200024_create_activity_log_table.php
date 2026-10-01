<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de spatie/laravel-activitylog (DATABASE.md §14.4).
 *
 * Sustituye al stub del paquete: morph keys en varchar(26) para ULID,
 * timestamptz y jsonb. El resto es idéntico al stub.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 26)->nullable();
            $table->index(['subject_type', 'subject_id'], 'subject');
            $table->string('event')->nullable();
            $table->string('causer_type')->nullable();
            $table->string('causer_id', 26)->nullable();
            $table->index(['causer_type', 'causer_id'], 'causer');
            $table->jsonb('attribute_changes')->nullable();
            $table->jsonb('properties')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
