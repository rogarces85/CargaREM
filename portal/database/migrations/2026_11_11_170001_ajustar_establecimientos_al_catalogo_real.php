<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajusta `establecimientos` al catálogo real (el DDL del M7 estaba marcado
 * como "ajustar nombres y longitudes al importar los catálogos reales").
 *
 * Lo que se encontró al importar:
 * - IdTipo NO es un código corto: trae hasta 49 caracteres ("CDM", "Posta de
 *   Salud Rural", etc.). El DDL proponía VARCHAR(20) y truncaba.
 * - IdComuna e IdDependencia son identificadores numéricos, no nombres. Se
 *   guardan como tales para no mentir con una columna llamada "comuna" que
 *   contiene "10301".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establecimientos', function (Blueprint $table) {
            $table->string('tipo', 50)->nullable()->change();
            $table->unsignedInteger('id_comuna')->nullable()->after('comuna');
            $table->unsignedInteger('id_dependencia')->nullable()->after('dependencia');
        });
    }

    public function down(): void
    {
        Schema::table('establecimientos', function (Blueprint $table) {
            $table->dropColumn(['id_comuna', 'id_dependencia']);
            $table->string('tipo', 20)->nullable()->change();
        });
    }
};
