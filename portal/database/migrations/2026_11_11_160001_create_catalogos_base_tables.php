<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogos base del portal (DAT-01 a DAT-04).
 *
 * Espejo del maestro del Minsal en MySQL: el servidor de producción no puede
 * leer Access, y la validación necesita consultas rápidas sobre ~185.000
 * prestaciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicios', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();
            $table->string('nombre', 150);
            $table->string('region', 80)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('establecimientos', function (Blueprint $table) {
            $table->id();
            // Puede contener guion: desde 2014 el Minsal usa códigos sin guion,
            // antes los usaba con guion (RN-M2-10).
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 200);
            $table->foreignId('servicio_id')->constrained('servicios')->restrictOnDelete();
            $table->string('comuna', 100)->nullable();
            $table->string('dependencia', 100)->nullable();
            $table->string('tipo', 20)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('servicio_id', 'ix_establecimientos_servicio');
        });

        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 5)->unique();   // A, BS, BM, D, P, F
            $table->string('nombre', 100);
            $table->smallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('periodos', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->date('fecha_limite')->nullable();
            $table->string('estado', 20)->default('abierto'); // abierto | en_revision | cerrado
            $table->timestamps();

            $table->unique(['anio', 'mes'], 'uq_periodos');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodos');
        Schema::dropIfExists('series');
        Schema::dropIfExists('establecimientos');
        Schema::dropIfExists('servicios');
    }
};
