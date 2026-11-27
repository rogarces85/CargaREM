<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices del alcance y del panel (v3, R-ALC-01 y RF-048).
 *
 * Tres consultas nuevas los necesitan:
 *
 * - «¿qué establecimientos tiene asignados este usuario?» se resuelve en cada
 *   petición, porque el alcance se aplica en la consulta y no en la vista. Sin
 *   el índice inverso del pivot, MySQL recorre la tabla completa.
 * - La bandeja y el panel filtran `envios` por establecimiento, año y mes.
 * - La matriz de completitud cruza establecimientos por comuna.
 *
 * `usuario_establecimiento` tiene la clave primaria `(usuario_id,
 * establecimiento_id)`: sirve para «lo de este usuario», no para «quién tiene
 * este establecimiento», que es justo la pregunta del reparto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuario_establecimiento', function (Blueprint $table) {
            $table->index(['establecimiento_id', 'usuario_id'], 'ix_ue_establecimiento');
        });

        Schema::table('envios', function (Blueprint $table) {
            $table->index(['establecimiento_id', 'anio', 'mes'], 'ix_envios_establecimiento_periodo');
            $table->index(['estado', 'anio', 'mes'], 'ix_envios_estado_periodo');
        });

        Schema::table('establecimientos', function (Blueprint $table) {
            $table->index('comuna', 'ix_establecimientos_comuna');
        });

        Schema::table('envio_archivos', function (Blueprint $table) {
            $table->index(['serie_id', 'es_vigente'], 'ix_archivo_serie_vigente');
        });
    }

    public function down(): void
    {
        Schema::table('usuario_establecimiento', function (Blueprint $table) {
            $table->dropIndex('ix_ue_establecimiento');
        });

        Schema::table('envios', function (Blueprint $table) {
            $table->dropIndex('ix_envios_establecimiento_periodo');
            $table->dropIndex('ix_envios_estado_periodo');
        });

        Schema::table('establecimientos', function (Blueprint $table) {
            $table->dropIndex('ix_establecimientos_comuna');
        });

        Schema::table('envio_archivos', function (Blueprint $table) {
            $table->dropIndex('ix_archivo_serie_vigente');
        });
    }
};
