<?php

namespace App\Validador;

/**
 * Resultado completo de validar un archivo (RF-040).
 *
 * Es lo que la pantalla muestra y lo que se guarda en `resultado` de la
 * ejecución. Incluye lo que el usuario necesita para entender el veredicto,
 * no solo el veredicto.
 */
final class ResultadoEstadistico
{
    /**
     * @param  array<string, mixed>  $establecimiento
     * @param  list<ResultadoRegla>  $resultados
     * @param  list<string>  $hojasAusentes
     */
    public function __construct(
        public readonly NombreArchivo $archivo,
        public readonly array $establecimiento,
        public readonly array $resultados,
        public readonly ResumenValidacion $resumen,
        public readonly ?string $errorVersion,
        public readonly array $hojasAusentes,
        public readonly bool $serieSoloPortada,
        public readonly int $duracionMs,
        public readonly CatalogoCalidad $catalogo,
    ) {
    }

    /** @return list<ResultadoRegla> solo los hallazgos, en orden de severidad */
    public function hallazgos(): array
    {
        $hallazgos = array_values(array_filter(
            $this->resultados,
            fn (ResultadoRegla $r) => $r->esHallazgo(),
        ));

        usort($hallazgos, function (ResultadoRegla $a, ResultadoRegla $b) {
            $orden = [ReglaCalidad::ERROR => 0, ReglaCalidad::REVISAR => 1, ReglaCalidad::INDICADOR => 2];

            return ($orden[$a->severidad] ?? 9) <=> ($orden[$b->severidad] ?? 9)
                ?: strcmp($a->reglaId, $b->reglaId);
        });

        return $hallazgos;
    }

    /** @return array<string, mixed> */
    public function aArreglo(): array
    {
        return [
            'metadatos' => [
                'nombre'       => $this->archivo->nombreOriginal,
                'codigo'       => $this->archivo->codigo,
                'serie'        => $this->archivo->serie,
                'mes'          => $this->archivo->mes,
                'extension'    => $this->archivo->extension,
                'establecimiento' => $this->establecimiento['nombre'] ?? null,
                'tipo'         => $this->establecimiento['tipo'] ?? null,
            ],
            'resumen'         => $this->resumen->aArreglo(),
            'errorVersion'    => $this->errorVersion,
            'hojasAusentes'   => $this->hojasAusentes,
            'serieSoloPortada' => $this->serieSoloPortada,
            'duracionMs'      => $this->duracionMs,
            'reglasVersion'   => $this->catalogo->version,
            'hallazgos'       => array_map(fn (ResultadoRegla $r) => $r->aArreglo(), $this->hallazgos()),
        ];
    }
}
