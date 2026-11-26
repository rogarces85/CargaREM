<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Meses en que se exige cada serie (RF-045, D-045).
 *
 * La serie P no es mensual: se informa dos veces al año. La estadística de
 * junio se entrega con el plazo de julio y la de diciembre con el de enero; en
 * los demás meses no se exige.
 *
 * Se modela como una lista de meses en la propia serie y no como una tabla de
 * calendario porque la regla es del catálogo, no del período: «la P se informa
 * en 6 y 12» no cambia de un año a otro. `null` significa **todos los meses**,
 * que es el caso de las otras cinco series.
 *
 * Un arreglo vacío no se usa: si un día una serie no se informa en ningún mes,
 * la respuesta correcta es desactivarla (`series.activo = false`), no dejarla
 * exigida y sin meses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->json('meses_exigidos')->nullable()->after('orden');
        });

        // La P es la única semestral del REM. Se actualiza también el catálogo
        // ya cargado: sin esto, una base existente seguiría exigiéndola todos
        // los meses y el cambio no llegaría a producción.
        DB::table('series')
            ->where('codigo', 'P')
            ->update(['meses_exigidos' => json_encode([6, 12])]);
    }

    public function down(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->dropColumn('meses_exigidos');
        });
    }
};
