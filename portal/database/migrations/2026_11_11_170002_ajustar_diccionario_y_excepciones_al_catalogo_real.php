<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajusta `diccionario` y `excepciones` a los anchos reales del catálogo.
 *
 * Medido sobre el snapshot del 2026-11-11:
 * - texto_prestacion llega a 255 caracteres; el DDL proponía 254 y el
 *   importador recortaba texto del Minsal.
 * - colexcep llega a 21 caracteres; `columna` era VARCHAR(10).
 * - La serie interna ("SERIE BS1") mide 9; el respaldo de `serie` necesitaba
 *   más de 5 aunque la serie externa quepa en 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diccionario', function (Blueprint $table) {
            $table->string('texto_prestacion', 300)->nullable()->change();
        });

        Schema::table('excepciones', function (Blueprint $table) {
            $table->string('serie', 10)->change();
            $table->string('columna', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('excepciones', function (Blueprint $table) {
            $table->string('serie', 5)->change();
            $table->string('columna', 10)->nullable()->change();
        });

        Schema::table('diccionario', function (Blueprint $table) {
            $table->string('texto_prestacion', 254)->nullable()->change();
        });
    }
};
