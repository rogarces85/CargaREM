<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calendario de días hábiles (D-029).
 *
 * - `feriados` guarda los días no hábiles además de sábados y domingos.
 * - `periodos.fecha_limite_manual` distingue la fecha calculada de la que el
 *   administrador fija por excepción.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feriados', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique();
            $table->string('nombre', 120);
            // Nacional o regional. El portal sirve a un Servicio (Osorno), así
            // que todo feriado registrado se aplica al calendario.
            $table->string('ambito', 20)->default('nacional');
            $table->string('region', 80)->nullable();
            $table->timestamps();
        });

        Schema::table('periodos', function (Blueprint $table) {
            $table->boolean('fecha_limite_manual')->default(false)->after('fecha_limite');
        });
    }

    public function down(): void
    {
        Schema::table('periodos', function (Blueprint $table) {
            $table->dropColumn('fecha_limite_manual');
        });

        Schema::dropIfExists('feriados');
    }
};
