<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las hojas se definen por año y **serie interna**, no por plantilla mensual.
 *
 * El Minsal maneja dos nombres para lo mismo:
 * - `SerieExcel` es lo que el archivo declara: "SERIE A".
 * - `Serie` es el nombre interno: "SERIE A1", "SERIE A2", "SERIE A".
 *
 * Las hojas (`Thojas`) y el diccionario (`Diccionario`) están indexados por el
 * nombre interno. Colapsar 15 internas en 6 externas juntaba hojas de
 * variantes distintas: de 1.129 filas de Thojas quedaban 672, y la variante
 * "A1" habría terminado exigiendo hojas de "A2" — faltantes falsos.
 *
 * Esta migración deja:
 * - `plantillas.serie_interna`, que es el nombre que usan los catálogos.
 * - `plantilla_hojas` colgando de (año, serie_interna, hoja).
 * - `diccionario.serie` con el nombre interno, que es el que consulta la
 *   validación.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('plantillas', 'serie_interna')) {
            Schema::table('plantillas', function (Blueprint $table) {
                $table->string('serie_interna', 10)->nullable()->after('serie_id');
            });
        }

        // Se reconstruye: la tabla venía de una migración a medias y su forma
        // anterior (plantilla_id) no sirve. Los datos se reimportan.
        Schema::dropIfExists('plantilla_hojas');

        Schema::create('plantilla_hojas', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('anio');
            $table->string('serie_interna', 10);
            $table->string('hoja', 100);
            $table->string('rango', 50)->nullable();
            $table->boolean('obligatoria')->default(true);

            $table->unique(['anio', 'serie_interna', 'hoja'], 'uq_ph_anio_serie_hoja');
            $table->index(['anio', 'serie_interna'], 'ix_ph_anio_serie');
        });

        Schema::table('diccionario', function (Blueprint $table) {
            $table->string('serie', 10)->change();
        });
    }

    public function down(): void
    {
        Schema::table('diccionario', function (Blueprint $table) {
            $table->string('serie', 5)->change();
        });

        Schema::dropIfExists('plantilla_hojas');

        Schema::create('plantilla_hojas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plantilla_id')->constrained('plantillas')->cascadeOnDelete();
            $table->string('hoja', 100);
            $table->string('rango', 50)->nullable();
            $table->boolean('obligatoria')->default(true);

            $table->index('plantilla_id', 'ix_ph_plantilla');
        });

        Schema::table('plantillas', function (Blueprint $table) {
            $table->dropColumn('serie_interna');
        });
    }
};
