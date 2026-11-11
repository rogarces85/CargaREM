<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de auditoría (DAT-16, principio 21).
 *
 * Append-only: no hay `updated_at` a propósito. Una fila escrita no se
 * modifica ni se borra desde la aplicación; el modelo lo impide y la interfaz
 * no ofrece esas acciones.
 *
 * No guarda contenido de celdas ni observaciones completas: los datos REM son
 * confidenciales (principio 22). Guarda identificadores y el hecho.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('evento', 50);
            $table->string('entidad', 100)->nullable();
            $table->string('entidad_id', 50)->nullable();
            $table->json('datos')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['entidad', 'entidad_id'], 'ix_auditoria_entidad');
            $table->index('usuario_id', 'ix_auditoria_usuario');
            $table->index('evento', 'ix_auditoria_evento');
            $table->index('created_at', 'ix_auditoria_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
    }
};
