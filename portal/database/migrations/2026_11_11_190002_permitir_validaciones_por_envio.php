<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los hallazgos de completitud (V11) son del envío, no de un archivo.
 *
 * Hasta ahora toda validación colgaba de un archivo obligatoriamente. V11
 * compara el conjunto —qué series exige la matriz y cuáles llegaron— así que
 * necesita filas sin archivo. Se agrega `envio_id` y se permite el nulo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('validaciones', function (Blueprint $table) {
            $table->foreignId('envio_id')->nullable()->after('id')->constrained('envios')->cascadeOnDelete();
            $table->index(['envio_id', 'etapa'], 'ix_val_envio');
        });

        Schema::table('validaciones', function (Blueprint $table) {
            $table->unsignedBigInteger('envio_archivo_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('validaciones', function (Blueprint $table) {
            $table->dropForeign(['envio_id']);
            $table->dropIndex('ix_val_envio');
            $table->dropColumn('envio_id');
        });
    }
};
