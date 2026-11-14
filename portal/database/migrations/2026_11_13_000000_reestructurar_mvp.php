<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cierre del alcance anterior y plazo configurable.
 *
 * Retira:
 * - la confirmación de carga: columna `envios.fecha_carga`.
 *
 * Agrega:
 * - `envios.fecha_limite`, foto del plazo del período al crear el envío;
 * - `envios.fuera_de_plazo`, que se marca al enviar a revisión.
 *
 * El catálogo de servicios y sus columnas NO se tocan: `MVP_V2.md` (14-11)
 * conserva RF-007 y el aislamiento del revisor por Servicio.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Un envío que estaba «cargado» pasa a «aprobado»: en el MVP v2 la
        // aprobación es el estado final.
        DB::table('envios')->where('estado', 'cargado')->update(['estado' => 'aprobado']);
        DB::table('envio_archivos')->where('estado', 'cargado')->update(['estado' => 'aprobado']);

        // Las transiciones hacia «cargado» ya no existen en el flujo.
        DB::table('envio_transiciones')->where('estado_nuevo', 'cargado')->delete();
        DB::table('envio_transiciones')->where('estado_anterior', 'cargado')->update(['estado_anterior' => 'aprobado']);

        Schema::table('envios', function (Blueprint $table) {
            $table->dropColumn('fecha_carga');
            $table->date('fecha_limite')->nullable()->after('fecha_aprobacion');
            $table->boolean('fuera_de_plazo')->nullable()->after('fecha_limite');
        });
    }

    public function down(): void
    {
        Schema::table('envios', function (Blueprint $table) {
            $table->dropColumn(['fecha_limite', 'fuera_de_plazo']);
            $table->dateTime('fecha_carga')->nullable();
        });
    }
};
