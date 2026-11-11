<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relaciones del catálogo (DAT-05, DAT-06).
 *
 * `establecimiento_serie` es la matriz que decide qué series se le exigen a
 * cada establecimiento. La vigencia permite cambiar la exigencia sin
 * reinterpretar meses ya cerrados (R-SER-03, R-SER-04).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('establecimiento_serie', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->cascadeOnDelete();
            $table->foreignId('serie_id')->constrained('series')->restrictOnDelete();
            $table->boolean('obligatoria')->default(true);
            $table->date('vigente_desde')->nullable();
            $table->date('vigente_hasta')->nullable();
            $table->timestamps();

            $table->index(['establecimiento_id', 'serie_id'], 'ix_es_establecimiento');
        });

        Schema::create('usuario_establecimiento', function (Blueprint $table) {
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['usuario_id', 'establecimiento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_establecimiento');
        Schema::dropIfExists('establecimiento_serie');
    }
};
