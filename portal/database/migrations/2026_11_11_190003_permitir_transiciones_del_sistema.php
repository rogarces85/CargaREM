<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una transición automática no tiene actor.
 *
 * `recalcular` pasa el envío a validado o con_errores sin intervención de
 * nadie. Antes la tabla exigía un actor y había que inventarlo; ahora el nulo
 * significa «lo hizo el sistema».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('envio_transiciones', function (Blueprint $table) {
            $table->unsignedBigInteger('actor_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('envio_transiciones', function (Blueprint $table) {
            $table->unsignedBigInteger('actor_id')->nullable(false)->change();
        });
    }
};
