<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alineación con `specs/MVP_V2.md` (14-11-2026).
 *
 * Retira del esquema lo que el documento deja fuera de v2.0:
 * - los snapshots de catálogo y su versión por archivo (`envio_archivos`);
 * - la tabla `excepciones` (V8);
 * - el estado `anulado` del envío.
 *
 * Repara además el catálogo de servicios si la base venía de la reestructura
 * intermedia de la rama (que lo eliminaba). Las guardas hacen que la misma
 * migración sirva en una base nueva y en una ya migrada a medias.
 *
 * `down()` reconstruye la estructura mínima para revertir el código, pero no
 * puede devolver las fotos de catálogo anteriores ni las filas de excepciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        // El estado «anulado» sale del flujo: un envío anulado vuelve a manos
        // del establecimiento (observado) y conserva su historia.
        DB::table('envios')->where('estado', 'anulado')->update(['estado' => 'observado']);

        $this->restaurarServicios();

        foreach ([
            'envio_archivos' => 'envio_archivos_snapshot_id_foreign',
            'plantillas'     => 'plantillas_snapshot_id_foreign',
        ] as $tabla => $claveForanea) {
            if (! Schema::hasColumn($tabla, 'snapshot_id')) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $table) use ($claveForanea) {
                $table->dropForeign($claveForanea);
                $table->dropColumn('snapshot_id');
            });
        }

        if (Schema::hasColumn('diccionario', 'snapshot_id')) {
            Schema::table('diccionario', function (Blueprint $table) {
                $table->dropForeign(['snapshot_id']);
                $table->dropIndex('ix_dicc_busqueda');
                $table->dropIndex('ix_dicc_prestacion');
                $table->dropColumn('snapshot_id');
            });

            Schema::table('diccionario', function (Blueprint $table) {
                $table->index(['anio', 'serie', 'hoja', 'linea'], 'ix_dicc_busqueda');
                $table->index('codigo_prestacion', 'ix_dicc_prestacion');
            });
        }

        Schema::dropIfExists('excepciones');
        Schema::dropIfExists('catalogo_snapshots');
    }

    public function down(): void
    {
        if (! Schema::hasTable('catalogo_snapshots')) {
            Schema::create('catalogo_snapshots', function (Blueprint $table) {
                $table->id();
                $table->string('origen', 150);
                $table->char('hash_catalogo', 64)->nullable();
                $table->unsignedInteger('filas_diccionario')->default(0);
                $table->unsignedInteger('filas_establecimientos')->default(0);
                $table->boolean('activo')->default(false);
                $table->foreignId('importado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('envio_archivos', 'snapshot_id')) {
            Schema::table('envio_archivos', function (Blueprint $table) {
                $table->foreignId('snapshot_id')->nullable()->constrained('catalogo_snapshots')->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('plantillas', 'snapshot_id')) {
            Schema::table('plantillas', function (Blueprint $table) {
                $table->foreignId('snapshot_id')->nullable()->constrained('catalogo_snapshots')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('diccionario', 'snapshot_id')) {
            Schema::table('diccionario', function (Blueprint $table) {
                $table->dropIndex('ix_dicc_busqueda');
                $table->dropIndex('ix_dicc_prestacion');
            });

            Schema::table('diccionario', function (Blueprint $table) {
                // Nullable en la reversión: no se puede garantizar el snapshot
                // de las filas que ya estaban cargadas sin él.
                $table->foreignId('snapshot_id')->nullable()->constrained('catalogo_snapshots')->cascadeOnDelete();
                $table->index(['snapshot_id', 'anio', 'serie', 'hoja', 'linea'], 'ix_dicc_busqueda');
                $table->index(['snapshot_id', 'codigo_prestacion'], 'ix_dicc_prestacion');
            });
        }
    }

    /**
     * Deja `servicios` y sus dos claves ajenas en el esquema.
     *
     * En una base nueva ya existen. En una base que corrió la reestructura
     * intermedia de la rama faltan; ahí se recrean y el administrador vuelve a
     * sembrar el catálogo con `CatalogoSeeder`.
     */
    private function restaurarServicios(): void
    {
        if (! Schema::hasTable('servicios')) {
            Schema::create('servicios', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 10)->unique();
                $table->string('nombre', 150);
                $table->string('region', 80)->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('establecimientos', 'servicio_id')) {
            Schema::table('establecimientos', function (Blueprint $table) {
                $table->foreignId('servicio_id')->nullable()->constrained('servicios')->nullOnDelete();
                $table->index('servicio_id', 'ix_establecimientos_servicio');
            });
        } else {
            Schema::table('establecimientos', function (Blueprint $table) {
                $table->dropForeign(['servicio_id']);
            });

            Schema::table('establecimientos', function (Blueprint $table) {
                // Un establecimiento puede existir antes de que el
                // administrador le asigne Servicio; el catálogo siempre lo trae.
                $table->unsignedBigInteger('servicio_id')->nullable()->change();
                $table->foreign('servicio_id')->references('id')->on('servicios')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('users', 'servicio_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('servicio_id')->nullable()->after('email')->constrained('servicios')->nullOnDelete();
            });
        }
    }
};
