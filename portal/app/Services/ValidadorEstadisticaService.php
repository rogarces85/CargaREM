<?php

namespace App\Services;

use App\Models\User;
use App\Models\ValidadorEjecucion;
use App\Validacion\BloqueoValidacion;
use App\Validador\ArchivoNoValidable;
use App\Validador\CatalogoCalidad;
use App\Validador\NominaEstablecimientos;
use App\Validador\ResultadoEstadistico;
use App\Validador\ValidadorEstadistico;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Módulo Validador (RF-040, D-047).
 *
 * Revisa un Excel REM suelto con el catálogo de reglas de la Unidad y registra
 * la ejecución. **No interviene en el proceso estadístico:** no crea envíos,
 * no cambia estados y no escribe en `validaciones`. Hay un test que lo
 * comprueba contando las cuatro tablas del flujo antes y después.
 *
 * El archivo se guarda en disco privado con nombre generado y se conserva 90
 * días (`rem:validador:limpiar`). La fila de registro se queda: es el dato de
 * uso que el administrador consulta.
 */
class ValidadorEstadisticaService
{
    /** Días que se guarda el archivo subido. */
    public const RETENCION_DIAS = 90;

    public function __construct(
        private readonly ValidadorEstadistico $validador,
        private readonly BloqueoValidacion $bloqueo,
    ) {
    }

    /**
     * Ejecuta el Validador sobre un archivo subido.
     *
     * La lectura del Excel pasa por el mismo semáforo que el flujo normal
     * (`BloqueoValidacion`): abrir un libro comprimido reserva cientos de megas
     * y el servidor solo admite dos a la vez (principio 25). Sin él, diez
     * envíos simultáneos al Validador tumbaban la memoria y arrastraban a las
     * validaciones de los demás usuarios.
     *
     * @return array{ejecucion: ValidadorEjecucion, resultado: ResultadoEstadistico}
     */
    public function ejecutar(User $usuario, UploadedFile $archivo): array
    {
        $inicio = microtime(true);
        $hash = hash_file('sha256', $archivo->getRealPath());
        $ruta = $this->guardar($archivo, $hash);

        try {
            $resultado = $this->bloqueo->conSlot(fn () => $this->validador->validar(
                Storage::disk(config('rem.archivos.disco'))->path($ruta),
                $archivo->getClientOriginalName(),
            ));
        } catch (ArchivoNoValidable $e) {
            // Un archivo que no entra no es un hallazgo: es un rechazo con
            // motivo. Se registra igual, para que el administrador vea que
            // alguien lo intentó y por qué no pasó.
            $ejecucion = $this->registrarRechazo($usuario, $archivo, $ruta, $hash, $e, $inicio);

            throw $e;
        } catch (Throwable $e) {
            $this->registrarRechazo(
                $usuario,
                $archivo,
                $ruta,
                $hash,
                new ArchivoNoValidable('No se pudo revisar el archivo: ' . $e->getMessage()),
                $inicio,
            );

            throw new ArchivoNoValidable('No se pudo revisar el archivo: ' . $e->getMessage(), [], 0, $e);
        }

        $ejecucion = $this->registrar($usuario, $archivo, $ruta, $hash, $resultado, $inicio);

        AuditoriaService::registrarSobre('validador.ejecutado', $ejecucion, [
            'reglas'      => $resultado->catalogo->version,
            'hallazgos'   => $resultado->resumen->hallazgos,
            'bloqueantes' => $resultado->resumen->erroresBloqueantes,
            'veredicto'   => $resultado->resumen->veredicto,
        ]);

        return ['ejecucion' => $ejecucion, 'resultado' => $resultado];
    }

    /**
     * Uso agregado por usuario y año (RF-040.2).
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function usoPorUsuario(?int $anio = null): \Illuminate\Support\Collection
    {
        $anio ??= (int) now()->year;

        return ValidadorEjecucion::query()
            ->selectRaw('usuario_id, COUNT(*) as ejecuciones, SUM(hallazgos) as hallazgos, SUM(bloqueantes) as bloqueantes, MAX(created_at) as ultima')
            ->whereYear('created_at', $anio)
            ->groupBy('usuario_id')
            ->with('usuario')
            ->orderByDesc('ejecuciones')
            ->get();
    }

    /** Ejecuciones por mes del año, para el resumen. */
    public function porMes(?int $anio = null): array
    {
        $anio ??= (int) now()->year;

        $filas = ValidadorEjecucion::query()
            ->selectRaw('MONTH(created_at) as mes, COUNT(*) as total')
            ->whereYear('created_at', $anio)
            ->groupBy('mes')
            ->pluck('total', 'mes')
            ->all();

        $serie = [];

        for ($mes = 1; $mes <= 12; $mes++) {
            $serie[$mes] = (int) ($filas[$mes] ?? 0);
        }

        return $serie;
    }

    /** Metadatos del catálogo, para mostrarle al usuario qué reglas corren. */
    public function catalogo(): ?CatalogoCalidad
    {
        try {
            return CatalogoCalidad::desdeArchivo();
        } catch (Throwable) {
            return null;
        }
    }

    public function nomina(): ?NominaEstablecimientos
    {
        try {
            return NominaEstablecimientos::desdeArchivo();
        } catch (Throwable) {
            return null;
        }
    }

    /** Borra los archivos con más de la retención; conserva el registro. */
    public function limpiarAntiguos(): int
    {
        $limite = now()->subDays(self::RETENCION_DIAS);
        $borrados = 0;

        ValidadorEjecucion::query()
            ->where('created_at', '<', $limite)
            ->where('ruta', 'not like', '%#borrado%')
            ->chunkById(200, function ($ejecuciones) use (&$borrados) {
                foreach ($ejecuciones as $ejecucion) {
                    try {
                        if (Storage::disk(config('rem.archivos.disco'))->exists($ejecucion->ruta)) {
                            Storage::disk(config('rem.archivos.disco'))->delete($ejecucion->ruta);
                        }
                    } catch (Throwable $e) {
                        Log::warning('No se pudo borrar el archivo del Validador.', [
                            'ejecucion' => $ejecucion->id,
                            'error'     => $e->getMessage(),
                        ]);

                        continue;
                    }

                    $ejecucion->update(['ruta' => $ejecucion->ruta . '#borrado']);
                    $borrados++;
                }
            });

        return $borrados;
    }

    private function registrar(
        User $usuario,
        UploadedFile $archivo,
        string $ruta,
        string $hash,
        ResultadoEstadistico $resultado,
        float $inicio,
    ): ValidadorEjecucion {
        $resumen = $resultado->resumen;

        return ValidadorEjecucion::create([
            'usuario_id'      => $usuario->id,
            'nombre_original' => mb_substr($archivo->getClientOriginalName(), 0, 255),
            'ruta'            => $ruta,
            'sha256'          => $hash,
            'tamano_bytes'    => (int) $archivo->getSize(),
            'reglas_version'  => $resultado->catalogo->version,
            'hallazgos'       => $resumen->hallazgos,
            'bloqueantes'     => $resumen->erroresBloqueantes,
            'advertencias'    => $resumen->revisar,
            'duracion_ms'     => (int) round((microtime(true) - $inicio) * 1000),
            'resultado'       => $resultado->aArreglo(),
            'estado'          => match (true) {
                $resumen->hallazgos === 0 => ValidadorEjecucion::SIN_HALLAZGOS,
                default                   => ValidadorEjecucion::CON_HALLAZGOS,
            },
        ]);
    }

    private function registrarRechazo(
        User $usuario,
        UploadedFile $archivo,
        string $ruta,
        string $hash,
        ArchivoNoValidable $error,
        float $inicio,
    ): ValidadorEjecucion {
        return ValidadorEjecucion::create([
            'usuario_id'      => $usuario->id,
            'nombre_original' => mb_substr($archivo->getClientOriginalName(), 0, 255),
            'ruta'            => $ruta,
            'sha256'          => $hash,
            'tamano_bytes'    => (int) $archivo->getSize(),
            'reglas_version'  => '—',
            'hallazgos'       => 0,
            'bloqueantes'     => 0,
            'advertencias'    => 0,
            'duracion_ms'     => (int) round((microtime(true) - $inicio) * 1000),
            'resultado'       => [
                'metadatos' => ['nombre' => $archivo->getClientOriginalName()],
                'resumen'   => [],
                'error'     => $error->getMessage(),
                'detalles'  => $error->detalles,
                'hallazgos' => [],
            ],
            'estado'          => ValidadorEjecucion::FALLIDO,
        ]);
    }

    /** Guarda el archivo en disco privado, con nombre generado. */
    private function guardar(UploadedFile $archivo, string $hash): string
    {
        $carpeta = sprintf('validador/%s/%s', now()->format('Y/m'), auth()->id() ?? 0);

        // El nombre en disco lo deriva el hash: el original se guarda en la
        // fila, pero nunca se usa como ruta (Constitución, principio 19).
        $nombre = $hash . '.' . strtolower($archivo->getClientOriginalExtension());

        return $archivo->storeAs($carpeta, $nombre, config('rem.archivos.disco'));
    }
}
