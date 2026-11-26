<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aperturas de fuera de plazo (RF-043, D-044).
 *
 * Una apertura es una ventana con motivo que habilita a un establecimiento a
 * cargar un período que de otro modo está cerrado. La pide el encargado o la
 * abre el administrador; **solo el administrador la resuelve**.
 *
 * Se modela como una fila por (establecimiento o todos, período) y no como un
 * cambio de estado del período: un período «cerrado» sigue cerrado para los
 * demás. Es la diferencia entre abrir la puerta de una oficina y cambiar el
 * horario del edificio.
 *
 * `establecimiento_id = null` significa **todos** los establecimientos del
 * Servicio. La ventana vence sola: no hace falta un proceso que la cierre,
 * aunque `rem:aperturas:cerrar` deja el estado explícito.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aperturas', function (Blueprint $table) {
            $table->id();

            // null = todos los establecimientos activos del Servicio.
            $table->foreignId('establecimiento_id')->nullable()->constrained('establecimientos')->cascadeOnDelete();

            $table->smallInteger('anio');
            $table->unsignedTinyInteger('mes');

            // Quién la pidió o la abrió, y con qué motivo.
            $table->string('origen', 20)->default('solicitud'); // solicitud | admin
            $table->string('motivo', 500);
            $table->foreignId('solicitado_por')->constrained('users')->restrictOnDelete();

            $table->string('estado', 20)->default('pendiente'); // pendiente | aprobada | rechazada | anulada

            $table->foreignId('resuelto_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resuelto_en')->nullable();
            $table->string('motivo_resolucion', 500)->nullable();

            // Hasta cuándo vale. Null = vale hasta que el administrador la anule.
            $table->date('vigente_hasta')->nullable();

            $table->timestamps();

            $table->index(['establecimiento_id', 'anio', 'mes', 'estado'], 'ix_aperturas_alcance');
            $table->index(['anio', 'mes', 'estado'], 'ix_aperturas_periodo');
        });

        // Trazabilidad del envío que se cargó al amparo de una apertura. Va en
        // `envios` y no en una tabla aparte porque la pregunta («¿este envío se
        // autorizó?») se hace siempre desde el envío.
        Schema::table('envios', function (Blueprint $table) {
            $table->foreignId('apertura_id')->nullable()->after('fuera_de_plazo')
                ->constrained('aperturas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('envios', function (Blueprint $table) {
            $table->dropForeign(['apertura_id']);
            $table->dropColumn('apertura_id');
        });

        Schema::dropIfExists('aperturas');
    }
};
