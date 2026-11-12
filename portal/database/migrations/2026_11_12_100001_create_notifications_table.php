<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de notificaciones de Laravel (T-F3-09).
 *
 * Las notificaciones se guardan además de enviarse: si el correo falla, el
 * usuario las ve igual al entrar al portal. Sin la tabla, un servidor de correo
 * caído dejaría al revisor sin enterarse de nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'ix_notifications_pendientes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};