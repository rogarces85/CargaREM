<?php

namespace App\Services;

use App\Excel\LectorCabecera;
use App\Models\CatalogoSnapshot;
use App\Models\Envio;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Serie;
use App\Models\Servicio;
use App\Models\User;
use App\Validacion\ResultadoValidacion;
use App\Validacion\ValidadorArchivo;
use Illuminate\Http\UploadedFile;

/**
 * Ejecuta un archivo REM real contra el motor (paridad y pruebas manuales).
 *
 * Toma establecimiento, serie y período de la cabecera del propio archivo, para
 * que el resultado sea el que tendría una carga real y no el de un escenario
 * inventado.
 */
class ParidadService
{
    public function __construct(
        private readonly LectorCabecera $cabeceras,
        private readonly EnvioService $envios,
        private readonly ValidadorArchivo $validador,
    ) {
    }

    /**
     * Sube (o reutiliza) el archivo y lo valida.
     *
     * @return array{resultado: ResultadoValidacion, envio: Envio, cabecera: \App\Excel\CabeceraArchivo, reutilizado: bool}
     */
    public function validarArchivo(string $ruta): array
    {
        $snapshot = CatalogoSnapshot::activo()->latest('id')->first();

        if ($snapshot === null) {
            throw new \RuntimeException('No hay catálogo activo. Importe uno con: php artisan catalogos:importar <ruta> --activar');
        }

        $cabecera = $this->cabeceras->leer($ruta);

        if (! $cabecera->identificable()) {
            throw new \RuntimeException('La cabecera no identifica establecimiento, serie, año y mes.');
        }

        $serie = Serie::where('codigo', strtoupper((string) $cabecera->serie))->first()
            ?? throw new \RuntimeException("La serie {$cabecera->serie} no está en el catálogo.");

        $servicio = Servicio::firstOrCreate(
            ['codigo' => '99'],
            ['nombre' => 'Servicio de prueba', 'activo' => true]
        );

        $establecimiento = Establecimiento::firstOrCreate(
            ['codigo' => (string) $cabecera->establecimiento],
            ['nombre' => "Establecimiento {$cabecera->establecimiento}", 'servicio_id' => $servicio->id, 'activo' => true]
        );

        // Solo la serie del archivo: la completitud no debe tapar el resultado.
        $establecimiento->series()->sync([$serie->id => ['obligatoria' => true]]);

        Periodo::firstOrCreate(
            ['anio' => (int) $cabecera->anio, 'mes' => (int) $cabecera->mes],
            ['estado' => Periodo::ABIERTO]
        );

        $usuario = User::where('email', 'admin@ssosorno.cl')->first() ?? User::first()
            ?? throw new \RuntimeException('No hay usuarios. Ejecute: php artisan db:seed');

        $envio = Envio::firstOrCreate(
            [
                'establecimiento_id' => $establecimiento->id,
                'anio'               => (int) $cabecera->anio,
                'mes'                => (int) $cabecera->mes,
            ],
            ['estado' => Envio::RECIBIDO, 'enviado_por' => $usuario->id, 'fecha_envio' => now()]
        );

        $hash = hash_file('sha256', $ruta);

        $existente = $envio->archivos()
            ->where('serie_id', $serie->id)
            ->where('sha256', $hash)
            ->latest('version')
            ->first();

        if ($existente !== null) {
            $registro = $existente;
            $reutilizado = true;
        } else {
            $registro = $this->envios->subirVersion(
                $envio,
                $serie,
                new UploadedFile($ruta, basename($ruta), null, null, true),
                $usuario
            );
            $reutilizado = false;
        }

        return [
            'resultado'   => $this->validador->validar($registro),
            'envio'       => $envio,
            'cabecera'    => $cabecera,
            'reutilizado' => $reutilizado,
        ];
    }

    /** @return array<string, int> código => cantidad */
    public static function contarPorCodigo(ResultadoValidacion $resultado): array
    {
        $conteo = [];

        foreach ($resultado->hallazgos as $hallazgo) {
            $conteo[$hallazgo->codigo] = ($conteo[$hallazgo->codigo] ?? 0) + 1;
        }

        ksort($conteo);

        return $conteo;
    }
}
