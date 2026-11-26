<?php

namespace App\Services;

use App\Models\User;
use App\Models\ValidadorEjecucion;
use App\Validador\CatalogoCalidad;
use App\Validador\HallazgoCalidad;
use App\Validador\MotorValidador;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Módulo Validador (RF-040, D-047).
 *
 * Revisa un Excel suelto con las reglas de calidad de la Unidad y registra la
 * ejecución. **No interviene en el proceso estadístico:** no crea envíos, no
 * cambia estados, no escribe en `validaciones`. Hay un test que lo comprueba.
 *
 * El archivo se guarda en disco privado con nombre generado y se conserva 90
 * días (`rem:validador:limpiar`). La fila de registro se queda: es el dato de
 * uso que el administrador consulta.
 */
class ValidadorEstadisticaService
{
    /** Días que se guarda el archivo subido. */
    public const RETENCION_DIAS = 90;

    public function __construct(private readonly MotorValidador $motor)
    {
    }

    /**
     * Ejecuta el Validador sobre un archivo subido.
     *
     * @return array{ejecucion: ValidadorEjecucion, catalogo: CatalogoCalidad}
     */
    public function ejecutar(User $usuario, UploadedFile $archivo): array
    {
        $catalogo = CatalogoCalidad::desdeArchivo();
        $inicio = microtime(true);

        $hash = hash_file('sha256', $archivo->getRealPath());
        $ruta = $this->guardar($archivo, $usuario);

        try {
            $hallazgos = $this->motor->ejecutar(
                Storage::disk(config('rem.archivos.disco'))->path($ruta),
                $catalogo
            );
        } catch (Throwable $e) {
            // Una ejecución fallida también se registra: si el archivo no se
            // puede abrir, el administrador tiene que poder verlo.
            $ejecucion = $this->registrar($usuario, $archivo, $ruta, $hash, $catalogo, [], $inicio, $e->getMessage());

            throw new \RuntimeException('No se pudo revisar el archivo: ' . $e->getMessage(), 0, $e);
        }

        $ejecucion = $this->registrar($usuario, $archivo, $ruta, $hash, $catalogo, $hallazgos, $inicio, null);

        AuditoriaService::registrarSobre('validador.ejecutado', $ejecucion, [
            'reglas'      => $catalogo->version,
            'hallazgos'   => $ejecucion->hallazgos,
            'bloqueantes' => $ejecucion->bloqueantes,
        ]);

        return ['ejecucion' => $ejecucion, 'catalogo' => $catalogo];
    }

    /**
     * @param  list<HallazgoCalidad>  $hallazgos
     */
    private function registrar(
        User $usuario,
        UploadedFile $archivo,
        string $ruta,
        string $hash,
        CatalogoCalidad $catalogo,
        array $hallazgos,
        float $inicio,
        ?string $error,
    ): ValidadorEjecucion {
        $bloqueantes = count(array_filter($hallazgos, fn (HallazgoCalidad $h) => $h->esBloqueante()));
        $advertencias = count($hallazgos) - $bloqueantes;

        $estado = match (true) {
            $error !== null => ValidadorEjecucion::FALLIDO,
            $bloqueantes > 0, count($hallazgos) > 0 => ValidadorEjecucion::CON_HALLAZGOS,
            default => ValidadorEjecucion::SIN_HALLAZGOS,
        };

        return ValidadorEjecucion::create([
            'usuario_id'      => $usuario->id,
            'nombre_original' => mb_substr($archivo->getClientOriginalName(), 0, 255),
            'ruta'            => $ruta,
            'sha256'          => $hash,
            'tamano_bytes'    => (int) $archivo->getSize(),
            'reglas_version'  => $catalogo->version,
            'hallazgos'       => count($hallazgos),
            'bloqueantes'     => $bloqueantes,
            'advertencias'    => $advertencias,
            'duracion_ms'     => (int) round((microtime(true) - $inicio) * 1000),
            'resultado'       => [
                'reglas'    => $catalogo->nombre,
                'error'     => $error,
                'hallazgos' => array_map(fn (HallazgoCalidad $h) => $h->aArreglo(), $hallazgos),
            ],
            'estado'          => $estado,
        ]);
    }

    /** Guarda el archivo en disco privado, con nombre generado. */
    private function guardar(UploadedFile $archivo, User $usuario): string
    {
        $carpeta = sprintf('validador/%s/%s', now()->format('Y/m'), $usuario->id);

        // `storeAs` con nombre derivado del hash: el nombre original se guarda
        // en la fila, pero en disco nunca se usa el que subió el usuario
        // (Constitución, principio 19).
        $nombre = hash_file('sha256', $archivo->getRealPath()) . '.' . $archivo->getClientOriginalExtension();

        return $archivo->storeAs($carpeta, $nombre, config('rem.archivos.disco'));
    }

    /**
     * Uso agregado por usuario y mes (RF-040.2).
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
}
