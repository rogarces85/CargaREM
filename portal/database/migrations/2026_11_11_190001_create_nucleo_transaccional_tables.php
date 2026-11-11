<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Núcleo transaccional del portal (T-F2-01, DAT-06 a DAT-10).
 *
 * Un envío es único por (establecimiento, año, mes): un mes se trabaja una
 * vez y los reenvíos son versiones de archivo, no envíos nuevos (RN-ENV-6).
 *
 * `envio_archivos` guarda cada versión con su hash y su snapshot de catálogo.
 * Nada se sobrescribe: el reenvío crea versión nueva y marca la anterior como
 * no vigente (RN-ENV-8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('envios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->restrictOnDelete();
            $table->smallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->string('estado', 20)->default('recibido');
            $table->foreignId('enviado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('observacion_general', 1000)->nullable();
            $table->dateTime('fecha_envio')->nullable();
            $table->dateTime('fecha_revision')->nullable();
            $table->dateTime('fecha_aprobacion')->nullable();
            $table->dateTime('fecha_carga')->nullable();
            $table->timestamps();

            $table->unique(['establecimiento_id', 'anio', 'mes'], 'uq_envios_periodo');
            $table->index('estado', 'ix_envios_estado');
            $table->index(['anio', 'mes'], 'ix_envios_periodo');
        });

        Schema::create('envio_archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('envio_id')->constrained('envios')->cascadeOnDelete();
            $table->foreignId('serie_id')->constrained('series')->restrictOnDelete();
            $table->unsignedSmallInteger('version')->default(1);
            $table->boolean('es_vigente')->default(true);
            $table->string('nombre_original', 255);
            $table->string('ruta', 500);
            $table->string('disco', 30)->default('local');
            $table->char('sha256', 64);
            $table->unsignedInteger('tamano_bytes');
            $table->string('mime', 100)->nullable();
            $table->string('estado', 20)->default('subido');
            $table->foreignId('plantilla_id')->nullable()->constrained('plantillas')->nullOnDelete();
            $table->foreignId('snapshot_id')->constrained('catalogo_snapshots')->restrictOnDelete();
            $table->unsignedInteger('filas_leidas')->nullable();
            $table->unsignedInteger('filas_validas')->nullable();
            $table->unsignedInteger('filas_error')->nullable();
            $table->dateTime('validado_en')->nullable();
            $table->foreignId('subido_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['envio_id', 'serie_id', 'version'], 'uq_archivo_version');
            $table->index(['envio_id', 'serie_id', 'es_vigente'], 'ix_archivo_vigente');
            $table->index('sha256', 'ix_archivo_hash');
        });

        Schema::create('validaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('envio_archivo_id')->constrained('envio_archivos')->cascadeOnDelete();
            $table->string('etapa', 5);              // V0 … V10
            $table->string('codigo', 20);            // ENV-001 …
            $table->string('severidad', 15);         // bloqueante | advertencia | info
            $table->string('regla', 60)->nullable(); // RN-M2-2, RN-M2-3, …
            $table->string('mensaje', 500);
            $table->string('hoja', 100)->nullable();
            $table->string('celda', 20)->nullable();
            $table->string('valor', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['envio_archivo_id', 'severidad'], 'ix_val_archivo');
        });

        Schema::create('observaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('envio_id')->constrained('envios')->cascadeOnDelete();
            $table->foreignId('envio_archivo_id')->nullable()->constrained('envio_archivos')->nullOnDelete();
            $table->foreignId('autor_id')->constrained('users')->restrictOnDelete();
            $table->string('texto', 2000);
            $table->boolean('resuelta')->default(false);
            $table->dateTime('resuelta_en')->nullable();
            $table->timestamps();

            $table->index(['envio_id', 'resuelta'], 'ix_obs_envio');
        });

        Schema::create('envio_transiciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('envio_id')->constrained('envios')->cascadeOnDelete();
            $table->string('estado_anterior', 20)->nullable();
            $table->string('estado_nuevo', 20);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('motivo', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['envio_id', 'created_at'], 'ix_trans_envio');
        });

        /*
         * Alcance del usuario.
         *
         * El revisor ve todo su Servicio de Salud, y hasta ahora no había dónde
         * guardar cuál es. Se agrega aquí porque sin esto el aislamiento de
         * RN-ENV-1 no se puede implementar en la consulta.
         */
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('servicio_id')->nullable()->after('email')->constrained('servicios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['servicio_id']);
            $table->dropColumn('servicio_id');
        });

        Schema::dropIfExists('envio_transiciones');
        Schema::dropIfExists('observaciones');
        Schema::dropIfExists('validaciones');
        Schema::dropIfExists('envio_archivos');
        Schema::dropIfExists('envios');
    }
};
