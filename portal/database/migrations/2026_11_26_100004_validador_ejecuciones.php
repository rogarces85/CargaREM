<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de uso del Validador (RF-040.1, D-047).
 *
 * El módulo no escribe en ninguna tabla del flujo de envío. Su rastro vive
 * aquí: quién lo usó, con qué archivo, con qué versión de reglas, qué encontró
 * y cuánto tardó. Sirve para dos cosas: que el administrador sepa si la
 * herramienta se usa, y que una revisión se pueda repetir con el mismo insumo.
 *
 * Los hallazgos se guardan como JSON en la propia fila y no en una tabla
 * aparte: son de una sola ejecución, nunca se consultan sueltos y borrar la
 * ejecución debe llevarse su resultado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('validador_ejecuciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();

            $table->string('nombre_original', 255);
            $table->string('ruta', 500);
            $table->char('sha256', 64);
            $table->unsignedInteger('tamano_bytes');

            $table->string('reglas_version', 30);
            $table->unsignedInteger('hallazgos')->default(0);
            $table->unsignedInteger('bloqueantes')->default(0);
            $table->unsignedInteger('advertencias')->default(0);
            $table->unsignedInteger('duracion_ms')->default(0);

            $table->json('resultado')->nullable();
            $table->string('estado', 20)->default('con_hallazgos'); // sin_hallazgos | con_hallazgos | fallido

            $table->timestamps();

            $table->index(['usuario_id', 'created_at'], 'ix_validador_usuario');
            $table->index('sha256', 'ix_validador_hash');
            $table->index('created_at', 'ix_validador_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validador_ejecuciones');
    }
};
