<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plantillas y snapshots de catálogo (DAT-11 a DAT-14).
 *
 * Un snapshot es una foto del catálogo importado. Cada archivo validado
 * apunta al snapshot con el que se validó, para que el resultado sea
 * reproducible aunque el Minsal publique una versión nueva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogo_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('origen', 100);            // p.ej. "Global.mdb 2026-11"
            $table->char('hash_paquete', 64)->nullable();
            $table->unsignedInteger('filas_diccionario')->nullable();
            $table->unsignedInteger('filas_establecimientos')->nullable();
            $table->boolean('activo')->default(false);
            $table->foreignId('importado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('plantillas', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->foreignId('serie_id')->constrained('series')->restrictOnDelete();
            // El Minsal maneja dos nombres: el interno (SERIE BS1) y el del
            // archivo (SERIE BS). Ambos se conservan.
            $table->string('serie_excel', 10);
            $table->string('version', 20);
            $table->foreignId('snapshot_id')->nullable()->constrained('catalogo_snapshots')->nullOnDelete();
            $table->timestamps();

            $table->unique(['anio', 'mes', 'serie_id', 'version'], 'uq_plantillas');
            $table->index(['anio', 'mes', 'serie_id'], 'ix_plantillas_periodo');
        });

        Schema::create('plantilla_hojas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plantilla_id')->constrained('plantillas')->cascadeOnDelete();
            $table->string('hoja', 100);
            $table->string('rango', 50)->nullable();  // rango declarado en Thojas
            $table->boolean('obligatoria')->default(true);

            $table->index('plantilla_id', 'ix_ph_plantilla');
        });

        Schema::create('plantilla_cabecera', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plantilla_id')->constrained('plantillas')->cascadeOnDelete();
            $table->string('campo', 30);              // serie | anio | mes | version | establecimiento
            $table->string('hoja', 100)->nullable();
            $table->string('celda', 10)->nullable();  // NULL = detección por etiqueta
            $table->string('etiqueta', 100)->nullable();
            $table->boolean('obligatorio')->default(true);

            $table->index('plantilla_id', 'ix_pc_plantilla');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantilla_cabecera');
        Schema::dropIfExists('plantilla_hojas');
        Schema::dropIfExists('plantillas');
        Schema::dropIfExists('catalogo_snapshots');
    }
};
