<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Espejo del Diccionario y de las excepciones de celda (DAT-15, DAT-16).
 *
 * `diccionario` es la tabla más grande del sistema: 185.526 filas en la
 * fuente. Sin los índices de búsqueda, la validación de un archivo no cabe
 * en el presupuesto de 90 segundos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diccionario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('catalogo_snapshots')->cascadeOnDelete();
            $table->smallInteger('anio');
            $table->unsignedTinyInteger('mes')->nullable();
            $table->string('serie', 5);
            $table->string('hoja', 100);
            $table->string('codigo_prestacion', 20);
            $table->string('texto_prestacion', 254)->nullable();
            $table->integer('linea');
            $table->string('inicio', 10)->nullable();
            $table->string('fin', 10)->nullable();
            $table->smallInteger('posicion')->nullable();
            $table->string('tipo_dato', 20)->nullable();

            $table->index(['snapshot_id', 'anio', 'serie', 'hoja', 'linea'], 'ix_dicc_busqueda');
            $table->index(['snapshot_id', 'codigo_prestacion'], 'ix_dicc_prestacion');
        });

        Schema::create('excepciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('catalogo_snapshots')->cascadeOnDelete();
            $table->smallInteger('anio');
            $table->unsignedTinyInteger('mes')->nullable();
            $table->string('serie', 5);
            $table->string('hoja', 100);
            $table->string('codigo_prestacion', 20)->nullable();
            $table->string('columna', 10)->nullable();
            $table->string('motivo', 200)->nullable();
            // Respaldo del esquema original de Excep/colexcep hasta que su
            // semántica se confirme (P6).
            $table->json('payload')->nullable();

            $table->index(['snapshot_id', 'anio', 'serie', 'hoja'], 'ix_excep_busqueda');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excepciones');
        Schema::dropIfExists('diccionario');
    }
};
