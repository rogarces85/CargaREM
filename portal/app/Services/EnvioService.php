<?php

namespace App\Services;

use App\Exceptions\ArchivoRechazadoException;
use App\Exceptions\EnvioInvalidoException;
use App\Models\CatalogoSnapshot;
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

        $existente = Envio::where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->first();

        if ($existente !== null) {
            throw EnvioInvalidoException::envioDuplicado($establecimiento->codigo, $anio, $mes);
        }

        return DB::transaction(function () use ($establecimiento, $anio, $mes, $usuario) {
            $envio = Envio::create([
                'establecimiento_id' => $establecimiento->id,
                'anio'               => $anio,
                'mes'                => $mes,
                'estado'             => Envio::RECIBIDO,
                'enviado_por'        => $usuario->id,
                'fecha_envio'        => now(),
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

        $snapshot = CatalogoSnapshot::activo()->latest('id')->first();

        if ($snapshot === null) {
            throw EnvioInvalidoException::sinCatalogo();
        }

        // ENV-029: el mismo contenido dos veces en el mismo envío es un error
        // del usuario, no una versión nueva.
        $hash = hash_file('sha256', $archivo->getRealPath());

        $repetido = $envio->archivos()->where('sha256', $hash)->exists();

        if ($repetido) {
            throw ArchivoRechazadoException::duplicado();
        }

        return DB::transaction(function () use ($envio, $serie, $archivo, $usuario, $snapshot) {
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
                'snapshot_id'     => $snapshot->id,
                'subido_por'      => $usuario->id,
            ]);

            if ($envio->estado !== Envio::RECIBIDO) {
                $envio->update(['estado' => Envio::RECIBIDO]);
            }

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
