<?php

namespace App\Services;

use App\Exceptions\ArchivoRechazadoException;
use App\Exceptions\EnvioInvalidoException;
use App\Models\Envio;
use App\Models\EnvioArchivo;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Serie;
use App\Models\User;
use App\Validacion\ValidadorArchivo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Alta de envíos y versionado de archivos (T-F2-03, T-F2-06).
 *
 * Un reenvío nunca sobrescribe: crea la versión siguiente, marca la anterior
 * como no vigente y deja la historia completa (RN-ENV-8).
 */
class EnvioService
{
    public function __construct(
        private readonly ArchivoService $archivos,
        private readonly ValidadorArchivo $validador,
        private readonly EstadoEnvioService $estados,
        private readonly ObservacionService $observaciones,
    ) {
    }

    /**
     * Crea el envío del mes para un establecimiento (OP-01).
     */
    public function crear(Establecimiento $establecimiento, int $anio, int $mes, User $usuario): Envio
    {
        $periodo = Periodo::where('anio', $anio)->where('mes', $mes)->first();

        if ($periodo === null) {
            throw EnvioInvalidoException::periodoNoExiste($anio, $mes);
        }

        if ($periodo->estado === Periodo::CERRADO) {
            throw EnvioInvalidoException::periodoCerrado($anio, $mes, $periodo->estado);
        }

        if ($periodo->estado === Periodo::EN_REVISION && ! $usuario->hasRole('admin')) {
            throw EnvioInvalidoException::periodoEnRevision($anio, $mes);
        }

        $existente = Envio::where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->first();

        if ($existente !== null) {
            throw EnvioInvalidoException::envioDuplicado($establecimiento->codigo, $anio, $mes);
        }

        return DB::transaction(function () use ($establecimiento, $anio, $mes, $usuario, $periodo) {
            $envio = Envio::create([
                'establecimiento_id' => $establecimiento->id,
                'anio'               => $anio,
                'mes'                => $mes,
                'estado'             => Envio::RECIBIDO,
                'enviado_por'        => $usuario->id,
                'fecha_envio'        => now(),
                // Sin fecha en el período no hay plazo que marcar: D4 sigue
                // abierta y el sistema no inventa un día por defecto.
                'fecha_limite'       => $periodo->fecha_limite?->toDateString(),
            ]);

            // La primera transición también se registra: sin ella, la historia
            // del envío no tiene origen.
            $envio->transiciones()->create([
                'estado_anterior' => null,
                'estado_nuevo'    => Envio::RECIBIDO,
                'actor_id'        => $usuario->id,
            ]);

            AuditoriaService::registrarSobre('envio.creado', $envio, [
                'establecimiento' => $establecimiento->codigo,
                'periodo'         => "{$mes}/{$anio}",
            ]);

            return $envio->load('establecimiento');
        });
    }

    /**
     * Sube una versión del archivo de una serie (OP-02).
     */
    public function subirVersion(
        Envio $envio,
        Serie $serie,
        UploadedFile $archivo,
        User $usuario,
    ): EnvioArchivo {
        if (! $serie->activo) {
            throw EnvioInvalidoException::serieNoAplicable($serie->codigo);
        }

        $periodo = Periodo::where('anio', $envio->anio)->where('mes', $envio->mes)->first();

        if ($periodo === null) {
            throw EnvioInvalidoException::periodoNoExiste($envio->anio, $envio->mes);
        }

        if ($periodo->estado === Periodo::CERRADO) {
            throw EnvioInvalidoException::periodoCerrado($envio->anio, $envio->mes, $periodo->estado);
        }

        if ($periodo->estado === Periodo::EN_REVISION
            && $envio->estado !== Envio::OBSERVADO
            && ! $usuario->hasRole('admin')) {
            throw EnvioInvalidoException::periodoEnRevision($envio->anio, $envio->mes);
        }

        // ENV-029: el mismo contenido dos veces en el mismo envío es un error
        // del usuario, no una versión nueva.
        $hash = hash_file('sha256', $archivo->getRealPath());

        $repetido = $envio->archivos()->where('sha256', $hash)->exists();

        if ($repetido) {
            throw ArchivoRechazadoException::duplicado();
        }

        return DB::transaction(function () use ($envio, $serie, $archivo, $usuario) {
            $version = ((int) $envio->archivos()->where('serie_id', $serie->id)->max('version')) + 1;

            // La versión anterior deja de ser la vigente, pero no se borra.
            $envio->archivos()
                ->where('serie_id', $serie->id)
                ->where('es_vigente', true)
                ->update(['es_vigente' => false]);

            // Se guarda el archivo antes de la fila: si falla el INSERT, el
            // archivo huérfano lo recoge la limpieza; al revés quedaría una
            // fila apuntando a la nada.
            $almacenado = $this->archivos->guardar($archivo, $envio, $serie, $version);

            $registro = $envio->archivos()->create([
                'serie_id'        => $serie->id,
                'version'         => $version,
                'es_vigente'      => true,
                'nombre_original' => $archivo->getClientOriginalName(),
                'ruta'            => $almacenado['ruta'],
                'disco'           => config('rem.archivos.disco'),
                'sha256'          => $almacenado['sha256'],
                'tamano_bytes'    => $almacenado['tamano_bytes'],
                'mime'            => $almacenado['mime'],
                'estado'          => EnvioArchivo::SUBIDO,
                'subido_por'      => $usuario->id,
            ]);

            // La observación hablaba del archivo anterior; con la versión
            // nueva queda resuelta (T-F3-02).
            $this->observaciones->resolverPorSerie($envio, $serie->id);

            // Un reenvío devuelve el envío a `recibido`: hay que revalidar.
            // Va por el servicio de estados para que el movimiento quede en
            // el historial; escribirlo a mano dejaba la historia incompleta.
            $this->estados->marcarRecibido($envio, $usuario);

            AuditoriaService::registrarSobre('archivo.subido', $registro, [
                'envio'   => $envio->id,
                'serie'   => $serie->codigo,
                'version' => $version,
                'sha256'  => $almacenado['sha256'],
            ]);

            return $registro;
        });
    }
/**
     * Revalida todos los archivos vigentes del envío (OP-03).
     *
     * @return array<int, \App\Validacion\ResultadoValidacion> clave: id del archivo
     */
    public function revalidar(Envio $envio): array
    {
        $resultados = [];

        foreach ($envio->archivosVigentes()->get() as $archivo) {
            $resultados[$archivo->id] = $this->validador->validar($archivo);
        }

        AuditoriaService::registrarSobre('envio.revalidado', $envio, [
            'archivos' => count($resultados),
        ]);

        return $resultados;
    }
}
