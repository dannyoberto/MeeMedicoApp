<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notificaciones del backoffice (la campana de Filament): avisos de procesos en cola,
 * como un lote de importación aplicado o una publicación en lote terminada.
 * Tabla de infraestructura de Laravel (DATABASE.md §17) con los ajustes de §3:
 *
 * - notifiable_id en varchar(26), para ULID (como activity_log).
 * - data en jsonb: Filament filtra por data->format.
 * - timestamptz.
 *
 * id se queda en uuid: lo genera el canal de notificaciones de Laravel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->string('notifiable_id', 26);
            $table->jsonb('data');
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();

            $table->index(['notifiable_type', 'notifiable_id'], 'notifications_notifiable_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
