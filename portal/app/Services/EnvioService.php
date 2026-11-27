<?php

namespace App\Services;

use App\Exceptions\ArchivoRechazadoException;
use App\Exceptions\EnvioInvalidoException;
use App\Models\Apertura;
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
        private readonly AperturaService $aperturas,
    ) {
    }

    /**
     * Crea el envío del mes para un establecimiento (OP-01).
     */
    public function crear(Establecimiento $establecimiento, int $anio, int $mes, User $usuario): Envio
    {
        // RF-046.1: el período que falta se crea al pedirlo, con su fecha
        // calculada. Antes el encargado tenía que esperar a que el
        // administrador lo creara desde el calendario.
        $periodo = $this->periodoDe($anio, $mes);

        // Alta nueva: no hay envío previo, así que no hay corrección que
        // amparar. Un período «en revisión» exige apertura.
        $apertura = $this->verificarCarga($periodo, $establecimiento, $usuario, null);

        $existente = Envio::where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->first();

        if ($existente !== null) {
            throw EnvioInvalidoException::envioDuplicado($establecimiento->codigo, $anio, $mes);
        }

        return DB::transaction(function () use ($establecimiento, $anio, $mes, $usuario, $periodo, $apertura) {
            $envio = Envio::create([
                'establecimiento_id' => $establecimiento->id,
                'anio'               => $anio,
                'mes'                => $mes,
                'estado'             => Envio::RECIBIDO,
                'enviado_por'        => $usuario->id,
                'fecha_envio'        => now(),
                // El período ya trae su fecha calculada (D-029). Se copia porque
                // un cambio posterior del período no debe reescribir el plazo de
                // un envío ya creado.
                'fecha_limite'       => $periodo->fecha_limite?->toDateString(),
                // Trazabilidad de la excepción: si se cargó con una apertura,
                // el envío lo dice y la pantalla puede explicarlo.
                'apertura_id'        => $apertura?->id,
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

        $periodo = $this->periodoDe((int) $envio->anio, (int) $envio->mes);

        $apertura = $this->verificarCarga($periodo, $envio->establecimiento, $usuario, $envio);

        // La carga al amparo de una apertura se recuerda en el envío, aunque la
        // apertura se anule después: el hecho ocurrió.
        if ($apertura !== null && $envio->apertura_id === null) {
            $envio->update(['apertura_id' => $apertura->id]);
        }

        // ENV-029: el mismo contenido dos veces en el mismo envío es un error
        // del usuario, no una versión nueva.
        $hash = hash_file('sha256', $archivo->getRealPath());

        $repetido = $envio->archivos()->where('sha256', $hash)->exists();

        if ($repetido) {
            throw ArchivoRechazadoException::duplicado();
        }

        $registro = DB::transaction(function () use ($envio, $serie, $archivo, $usuario) {
            $version = ((int) $envio->archivos()->where('serie_id', $serie->id)->max('version')) + 1;

            // La versión anterior deja de ser la vigente, pero no se borra.
            // RN-ENV-8 / REVIEW.md §8: el reenvío NO reemplaza la fila
            // anterior, la marca. Sin este estado el histórico se conserva
            // pero la interfaz no puede distinguir «reemplazada» de «vigente
            // en su momento».
            $envio->archivos()
                ->where('serie_id', $serie->id)
                ->where('es_vigente', true)
                ->update([
                    'es_vigente' => false,
                    'estado'     => EnvioArchivo::REEMPLAZADO,
                ]);

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

        // El aviso va fuera de la transacción, igual que la transición de
        // estado: si la campana falla, el archivo ya está guardado.
        $this->notificarSubida($envio->fresh(), $serie, $usuario);

        return $registro;
    }

    /**
     * Avisa al Equipo REM de la subida (RF-041).
     *
     * Un fallo del notificador nunca revierte la subida: el archivo es el dato
     * y el aviso es la cortesía.
     */
    private function notificarSubida(Envio $envio, Serie $serie, User $usuario): void
    {
        try {
            app(Notificador::class)->subida($envio, $serie, $usuario);
        } catch (\Throwable $e) {
            report($e);
        }
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

    /**
     * El período del mes, creándolo si no existe (RF-046.1).
     *
     * La fecha límite la calcula el propio modelo al guardar (`Periodo::saving`),
     * con el calendario de feriados. Así el portal funciona igual con cron o
     * sin él: no hay un proceso del que dependa la creación.
     */
    private function periodoDe(int $anio, int $mes): Periodo
    {
        $periodo = Periodo::where('anio', $anio)->where('mes', $mes)->first();

        if ($periodo !== null) {
            return $periodo;
        }

        if ($anio < 2000 || $mes < 1 || $mes > 12) {
            throw EnvioInvalidoException::periodoNoExiste($anio, $mes);
        }

        return Periodo::create([
            'anio'   => $anio,
            'mes'    => $mes,
            'estado' => Periodo::ABIERTO,
        ]);
    }

    /**
     * ¿Se puede cargar en este período para este establecimiento?
     *
     * Devuelve la apertura que lo habilita, o null si no hacía falta ninguna.
     * Lanza la excepción que corresponde cuando la carga no está permitida.
     *
     * Reglas (`specs/MVP_V3.md` §8):
     * - `cerrado`: nadie carga, salvo con apertura vigente (R-APL-03).
     * - `en_revision`: solo se aceptan correcciones de envíos observados; un
     *   envío nuevo o una subida a otro estado exige apertura.
     */
    private function verificarCarga(Periodo $periodo, Establecimiento $establecimiento, User $usuario, ?Envio $envio = null): ?Apertura    {
        if ($usuario->hasRole('admin')) {
            return null;
        }

        $cerrado = $periodo->estado === Periodo::CERRADO;
        $enRevision = $periodo->estado === Periodo::EN_REVISION;

        if (! $cerrado && ! $enRevision) {
            return null;
        }

        // En revisión, un envío observado sigue su curso normal: es la
        // corrección que el propio flujo pidió.
        if ($enRevision && $envio !== null && $envio->estado === Envio::OBSERVADO) {
            return null;
        }

        if ($enRevision && $usuario->hasRole('admin')) {
            return null;
        }

        $apertura = $this->aperturas->vigentePara($establecimiento, (int) $periodo->anio, (int) $periodo->mes);

        if ($apertura !== null) {
            return $apertura;
        }

        throw $cerrado
            ? EnvioInvalidoException::periodoCerrado((int) $periodo->anio, (int) $periodo->mes, $periodo->estado)
            : EnvioInvalidoException::periodoEnRevision((int) $periodo->anio, (int) $periodo->mes);
    }
}
