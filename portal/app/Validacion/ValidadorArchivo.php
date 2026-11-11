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
     */
    public function validar(EnvioArchivo $archivo): ResultadoValidacion
    {
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

    /** @param list<Hallazgo> $hallazgos */
    private function estadoDe(EnvioArchivo $archivo, array $hallazgos): string
    {
        $bloqueantes = count(array_filter($hallazgos, fn (Hallazgo $h) => $h->esBloqueante()));

        return $bloqueantes === 0 ? EnvioArchivo::VALIDO : EnvioArchivo::CON_ERRORES;
    }

    /** @param list<Hallazgo> $hallazgos */
    private function persistir(EnvioArchivo $archivo, array $hallazgos): void
    {
        DB::transaction(function () use ($archivo, $hallazgos) {
            // Revalidar regenera el reporte: los hallazgos anteriores dejan de
            // valer porque describían otra ejecución.
            $archivo->validaciones()->delete();

            foreach ($hallazgos as $hallazgo) {
                DB::table('validaciones')->insert($hallazgo->aFila($archivo->id) + [
                    'created_at' => now(),
                ]);
            }
        });
    }
}
