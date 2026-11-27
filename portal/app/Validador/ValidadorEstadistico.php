<?php

namespace App\Validador;

/**
 * Validación completa de un archivo REM (RF-040).
 *
 * Orquesta el pipeline del Validador de escritorio de la Unidad, en el mismo
 * orden, porque el orden importa:
 *
 * 1. Metadatos del nombre del archivo.
 * 2. Lectura del libro.
 * 3. Hojas obligatorias de la serie.
 * 4. Establecimiento en la nómina (sin tipo, las reglas por tipo quedarían
 *    fuera de alcance en silencio).
 * 5. Hoja NOMBRE.
 * 6. Reglas de negocio de la serie.
 *
 * **No toca nada del portal.** Recibe una ruta y devuelve un resultado; no
 * conoce `Envio` ni escribe en base de datos. Esa es la garantía que se prueba
 * en `ModuloValidadorTest`.
 */
final class ValidadorEstadistico
{
    public function __construct(
        private readonly MotorReglas $motor,
        private readonly HojaNombre $hojaNombre,
    ) {
    }

    /**
     * @return array{resultados: list<ResultadoRegla>, resumen: ResumenValidacion, metadatos: nombre, ...}
     */
    public function validar(
        string $ruta,
        string $nombreOriginal,
        ?CatalogoCalidad $catalogo = null,
        ?NominaEstablecimientos $nomina = null,
    ): ResultadoEstadistico {
        $inicio = microtime(true);

        $catalogo ??= CatalogoCalidad::desdeArchivo();
        $nomina ??= NominaEstablecimientos::desdeArchivo();

        // 1. Metadatos del nombre del archivo.
        $archivo = NombreArchivo::parsear($nombreOriginal);

        if (! $archivo->esValido()) {
            throw new ArchivoNoValidable(
                'No se puede validar el archivo por su nombre.',
                $archivo->errores,
            );
        }

        // 2. Lectura del libro.
        $libro = new LectorSpreadsheet($ruta);

        try {
            try {
                $hojas = $libro->hojas();
            } catch (\Throwable $e) {
                throw new ArchivoNoValidable(
                    'El archivo no pudo abrirse como libro de Excel. Puede estar dañado o protegido con contraseña.',
                    [$e->getMessage()],
                );
            }

            // 3. Hojas obligatorias de la serie.
            $faltantes = $archivo->hojasObligatoriasFaltantes($hojas);

            if ($faltantes !== []) {
                $cuantas = count($faltantes) === 1 ? 'la hoja obligatoria' : 'las hojas obligatorias';

                throw new ArchivoNoValidable(
                    'El archivo de la serie ' . $archivo->serie . ' no contiene ' . $cuantas . ': ' . implode(', ', $faltantes) . '.',
                    ['Verifique que corresponde a la plantilla oficial de la serie.'],
                );
            }

            // 4. Establecimiento.
            $establecimiento = $nomina->buscar($archivo->codigo);

            if ($establecimiento === null) {
                throw new ArchivoNoValidable(
                    'El establecimiento ' . $archivo->codigo . ' no corresponde al ' . $nomina->servicio . '.',
                    [
                        'Revise el nombre del archivo: el código debe ser el DEIS de un recinto de la red.',
                        'Si el recinto es nuevo, solicite incorporarlo a la nómina del validador.',
                    ],
                );
            }

            // 5. Hoja NOMBRE.
            $portada = $this->hojaNombre->revisar($libro, $archivo, $nomina);

            // 6. Reglas de negocio de la serie.
            $reglasSerie = $catalogo->reglasDeSerie($archivo->serie);

            $hojasPresentes = array_map(fn ($h) => mb_strtoupper(trim((string) $h)), $hojas);
            $hojasAusentes = [];
            $evaluables = [];

            foreach ($reglasSerie as $regla) {
                $faltan = array_values(array_filter(
                    $regla->hojasRequeridas(),
                    fn (string $h) => ! in_array(mb_strtoupper($h), $hojasPresentes, true),
                ));

                if ($faltan !== []) {
                    foreach ($faltan as $hoja) {
                        $hojasAusentes[$hoja] = true;
                    }

                    continue;
                }

                $evaluables[] = $regla;
            }

            $contexto = new ContextoEstablecimiento($archivo->codigo, (string) $establecimiento['tipo']);

            $resultados = [];
            $fueraDeAlcance = 0;

            foreach ($evaluables as $regla) {
                if (! $this->motor->aplica($regla, $contexto)) {
                    $fueraDeAlcance++;

                    continue;
                }

                $resultados[] = $this->motor->evaluar($regla, $libro);
            }

            // La portada va primero: si está mal, condiciona todo lo demás.
            $todos = [...$portada['resultados'], ...$resultados];

            $resumen = ResumenValidacion::desde(
                $todos,
                fueraDeAlcance: $fueraDeAlcance,
                hojasAusentes: count($evaluables) === 0 ? 0 : count($reglasSerie) - count($evaluables),
            );

            return new ResultadoEstadistico(
                archivo: $archivo,
                establecimiento: $establecimiento,
                resultados: $todos,
                resumen: $resumen,
                errorVersion: $portada['errorVersion'],
                hojasAusentes: array_keys($hojasAusentes),
                serieSoloPortada: $reglasSerie === [],
                duracionMs: (int) round((microtime(true) - $inicio) * 1000),
                catalogo: $catalogo,
            );
        } finally {
            $libro->cerrar();
        }
    }
}
