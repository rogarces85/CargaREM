<?php

namespace App\Validacion;

use App\Models\EnvioArchivo;
use App\Models\Plantilla;
use App\Validacion\Reglas\ReglaV0Archivo;
use App\Validacion\Reglas\ReglaV1Nombre;
use App\Validacion\Reglas\ReglaV2Cabecera;
use App\Validacion\Reglas\ReglaV3Version;
use App\Validacion\Reglas\ReglaV4Pertenencia;
use App\Validacion\Reglas\ReglaV5Hojas;
use App\Validacion\Reglas\ReglaV6Datos;
use App\Validacion\Reglas\ReglaV7TipoDato;
use App\Validacion\Reglas\ReglaV8Excepciones;
use App\Validacion\Reglas\ReglaV9Duplicados;
use App\Validacion\Reglas\ReglaV10RegistroValido;
use App\Validacion\Reglas\ReglaV11Completitud;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Ejecuta el pipeline V0–V11 sobre un archivo (T-F2-12, T-F2-23).
 *
 * Dos reglas del juego:
 * 1. Un bloqueante detiene la cadena: no tiene sentido buscar prestaciones en
 *    un archivo cuya cabecera no se pudo leer.
 * 2. V11 (completitud del envío) siempre se ejecuta, porque no depende del
 *    archivo sino del conjunto.
 */
class ValidadorArchivo
{
    /** @var list<class-string<ReglaValidacion>> */
    private array $reglas = [
        ReglaV0Archivo::class,
        ReglaV1Nombre::class,
        ReglaV2Cabecera::class,
        ReglaV3Version::class,
        ReglaV4Pertenencia::class,
        ReglaV5Hojas::class,
        ReglaV6Datos::class,
        ReglaV7TipoDato::class,
        ReglaV8Excepciones::class,
        ReglaV9Duplicados::class,
        ReglaV10RegistroValido::class,
        ReglaV11Completitud::class,
    ];

    /** Permite a los tests inyectar un conjunto propio de reglas. */
    public function conReglas(array $reglas): static
    {
        $this->reglas = $reglas;

        return $this;
    }

    /** @return list<ReglaValidacion> */
    private function instancias(): array
    {
        return array_map(fn (string $clase) => app($clase), $this->reglas);
    }

    /**
     * Valida un archivo y deja el resultado en base de datos.
     *
     * Ocupa uno de los huecos de validación concurrente: abrir un Excel
     * consume memoria, y el servidor solo aguanta dos a la vez.
     */
    public function validar(EnvioArchivo $archivo): ResultadoValidacion
    {
        return app(BloqueoValidacion::class)->conSlot(fn () => $this->validarSinBloqueo($archivo));
    }

    private function validarSinBloqueo(EnvioArchivo $archivo): ResultadoValidacion
    {
        $inicio = microtime(true);

        $archivo->loadMissing('envio.establecimiento', 'serie');

        $ruta = Storage::disk($archivo->disco)->path($archivo->ruta);

        $plantilla = Plantilla::query()
            ->where('anio', $archivo->envio->anio)
            ->where('mes', $archivo->envio->mes)
            ->where('serie_id', $archivo->serie_id)
            ->orderByDesc('id')
            ->first();

        $contexto = new ContextoValidacion(
            envio: $archivo->envio,
            archivo: $archivo,
            serie: $archivo->serie,
            rutaAbsoluta: $ruta,
            plantilla: $plantilla,
            snapshotId: (int) $archivo->snapshot_id,
        );

        $hallazgos = [];

        foreach ($this->instancias() as $regla) {
            $nuevos = $regla->evaluar($contexto);
            $hallazgos = array_merge($hallazgos, $nuevos);

            $hayBloqueante = count(array_filter($nuevos, fn (Hallazgo $h) => $h->esBloqueante())) > 0;

            if ($hayBloqueante && $regla->etapa() !== 'V11') {
                break;
            }
        }

        /*
         * ENV-030: la validación excedió el tiempo disponible.
         *
         * Se comprueba al final y se agrega como bloqueante: si se pasó del
         * límite, el resultado puede estar incompleto y no debe aprobarse.
         */
        $limite = (int) config('rem.validacion.timeout_segundos');

        if ($limite > 0 && (microtime(true) - $inicio) > $limite) {
            $hallazgos[] = new Hallazgo(
                'V11', 'ENV-030', Severidad::BLOQUEANTE,
                "La validación tardó más de {$limite} segundos: vuelva a intentarlo.",
                null, null, null, 'R-V11-04'
            );
        }

        $this->persistir($archivo, $hallazgos);

        $resultado = new ResultadoValidacion(
            $hallazgos,
            $this->estadoDe($archivo, $hallazgos),
        );

        $archivo->update([
            'estado'      => $resultado->estado,
            'validado_en' => now(),
        ]);

        return $resultado;
    }

    /**
     * Estado del archivo: solo cuenta lo que le pasa AL ARCHIVO.
     *
     * Los hallazgos de V11 son del envío —«falta la serie BS»— y no deben
     * marcar como defectuoso el archivo de la serie A. Sin esta separación,
     * un envío incompleto marcaba todos sus archivos como con errores y V11
     * volvía a quejarse de ellos: un círculo.
     *
     * @param list<Hallazgo> $hallazgos
     */
    private function estadoDe(EnvioArchivo $archivo, array $hallazgos): string
    {
        $propios = array_filter($hallazgos, fn (Hallazgo $h) => $h->etapa !== 'V11');
        $bloqueantes = count(array_filter($propios, fn (Hallazgo $h) => $h->esBloqueante()));

        return $bloqueantes === 0 ? EnvioArchivo::VALIDO : EnvioArchivo::CON_ERRORES;
    }

    /**
     * Persiste los hallazgos.
     *
     * Los de V11 son del envío, no del archivo: se guardan con `envio_id` y sin
     * archivo, y se reemplazan enteros en cada pasada para no acumular copias
     * una por cada archivo validado.
     *
     * @param list<Hallazgo> $hallazgos
     */
    private function persistir(EnvioArchivo $archivo, array $hallazgos): void
    {
        DB::transaction(function () use ($archivo, $hallazgos) {
            // Revalidar regenera el reporte: los hallazgos anteriores dejan de
            // valer porque describían otra ejecución.
            $archivo->validaciones()->delete();
            DB::table('validaciones')->where('envio_id', $archivo->envio_id)->where('etapa', 'V11')->delete();

            foreach ($hallazgos as $hallazgo) {
                $fila = $hallazgo->aFila($archivo->id);

                if ($hallazgo->etapa === 'V11') {
                    $fila['envio_archivo_id'] = null;
                    $fila['envio_id'] = $archivo->envio_id;
                }

                DB::table('validaciones')->insert($fila + ['created_at' => now()]);
            }
        });
    }
}
