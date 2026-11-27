<?php

namespace App\Validador;

/**
 * Filtros del listado de resultados del Validador (RF-040.4).
 *
 * En el escritorio esto vive en React y filtra un array en memoria; aquí la
 * lógica es la misma y por la misma razón: una revisión tiene decenas de
 * resultados, no miles, así que filtrar en PHP es más simple que consultar.
 *
 * Tres decisiones que se ven en el código y no en la pantalla:
 *
 * - **Se abre en lo que hay que corregir.** Los estados iniciales son HALLAZGO
 *   y ERROR_TECNICO: al entrar interesa lo que falla, y lo que cumple queda a
 *   un clic. Abrir con todo obliga a filtrar en cada revisión.
 * - **La búsqueda ignora tildes y mayúsculas.** El catálogo trae «Presión»,
 *   «Código», «Sección»; quien busca escribe «presion», «codigo», «seccion».
 * - **Todos los términos deben aparecer.** Buscar «A01 presion» acota, no
 *   amplía: es lo que espera quien sigue escribiendo para afinar.
 */
final class FiltroResultados
{
    public const INICIALES = ['HALLAZGO', 'ERROR_TECNICO'];

    public const ESTADOS = ['HALLAZGO', 'CUMPLE', 'OMITIDA', 'ERROR_TECNICO'];

    public const SEVERIDADES = ['ERROR', 'REVISAR', 'INDICADOR'];

    /**
     * @param  list<string>  $estados
     * @param  list<string>  $severidades
     */
    public function __construct(
        public readonly array $estados = self::INICIALES,
        public readonly array $severidades = [],
        public readonly ?string $hoja = null,
        public readonly string $busqueda = '',
        public readonly ?string $detalle = null,
    ) {
    }

    /** Construye desde los parámetros de la URL, descartando lo que no conoce. */
    public static function desdeConsulta(array $consulta): self
    {
        $estados = self::limpiar(
            (array) ($consulta['estados'] ?? self::INICIALES),
            self::ESTADOS,
        );

        $severidades = self::limpiar(
            (array) ($consulta['severidades'] ?? []),
            self::SEVERIDADES,
        );

        $hoja = isset($consulta['hoja']) && $consulta['hoja'] !== ''
            ? (string) $consulta['hoja']
            : null;

        return new self(
            estados: $estados,
            severidades: $severidades,
            hoja: $hoja,
            busqueda: trim((string) ($consulta['q'] ?? '')),
            detalle: isset($consulta['ver']) && $consulta['ver'] !== ''
                ? (string) $consulta['ver']
                : null,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $resultados
     * @return list<array<string, mixed>>
     */
    public function aplicar(array $resultados): array
    {
        $terminos = $this->terminos();

        return array_values(array_filter($resultados, function (array $r) use ($terminos) {
            if ($this->estados !== [] && ! in_array($r['estado'] ?? '', $this->estados, true)) {
                return false;
            }

            if ($this->severidades !== [] && ! in_array($r['severidad'] ?? '', $this->severidades, true)) {
                return false;
            }

            if ($this->hoja !== null && ($r['hoja'] ?? '') !== $this->hoja) {
                return false;
            }

            if ($terminos !== []) {
                $texto = $this->textoBuscable($r);

                foreach ($terminos as $termino) {
                    if (! str_contains($texto, $termino)) {
                        return false;
                    }
                }
            }

            return true;
        }));
    }

    /** ¿Hay algún filtro puesto que no sea el de partida? */
    public function hayFiltrosActivos(): bool
    {
        return $this->severidades !== []
            || $this->hoja !== null
            || $this->busqueda !== ''
            || $this->estadosOrdenados() !== self::ordenar(self::INICIALES);
    }

    /** Copia con un estado alternado: si estaba, sale; si no, entra. */
    public function conEstado(string $estado): self
    {
        $estados = $this->alternar($this->estados, $estado, self::ESTADOS);

        return new self($estados, $this->severidades, $this->hoja, $this->busqueda, $this->detalle);
    }

    /** Copia con una severidad alternada. */
    public function conSeveridad(string $severidad): self
    {
        $severidades = $this->alternar($this->severidades, $severidad, self::SEVERIDADES);

        return new self($this->estados, $severidades, $this->hoja, $this->busqueda, $this->detalle);
    }

    /**
     * Copia con el texto de una regla a la vista, manteniendo el resto del
     * filtro: abrir un resultado no puede borrar lo que se venía filtrando.
     */
    public function conDetalle(?string $regla): self
    {
        return new self($this->estados, $this->severidades, $this->hoja, $this->busqueda, $regla);
    }

    /**
     * Parámetros de la URL que representan este filtro.
     *
     * Los estados iniciales no viajan: dejarlos fuera de la URL mantiene el
     * «Ver todo» y el enlace limpio con la misma apariencia.
     *
     * @return array<string, mixed>
     */
    public function aConsulta(): array
    {
        $consulta = [];

        if ($this->estadosOrdenados() !== self::ordenar(self::INICIALES)) {
            $consulta['estados'] = $this->estados;
        }

        if ($this->severidades !== []) {
            $consulta['severidades'] = $this->severidades;
        }

        if ($this->hoja !== null) {
            $consulta['hoja'] = $this->hoja;
        }

        if ($this->busqueda !== '') {
            $consulta['q'] = $this->busqueda;
        }

        if ($this->detalle !== null) {
            $consulta['ver'] = $this->detalle;
        }

        return $consulta;
    }

    /**
     * @param  list<string>  $lista
     * @param  list<string>  $permitidos
     * @return list<string>
     */
    private function alternar(array $lista, string $valor, array $permitidos): array
    {
        if (! in_array($valor, $permitidos, true)) {
            return $lista;
        }

        $resultado = in_array($valor, $lista, true)
            ? array_values(array_filter($lista, fn ($v) => $v !== $valor))
            : [...$lista, $valor];

        $orden = array_flip($permitidos);

        usort($resultado, fn ($a, $b) => ($orden[$a] ?? 99) <=> ($orden[$b] ?? 99));

        return $resultado;
    }

    /**
     * Hojas REM presentes en la revisión, con cuántos hallazgos aporta cada una.
     *
     * Se calcula sobre **todos** los resultados, no sobre los filtrados: el
     * desplegable no puede encogerse a medida que se filtra, o deja de servir
     * para navegar.
     *
     * @param  list<array<string, mixed>>  $resultados
     * @return list<array{hoja: string, hallazgos: int, total: int}>
     */
    public static function conteoPorHoja(array $resultados): array
    {
        $mapa = [];

        foreach ($resultados as $r) {
            $hoja = (string) ($r['hoja'] ?? '');

            if ($hoja === '') {
                continue;
            }

            $mapa[$hoja] ??= ['hoja' => $hoja, 'hallazgos' => 0, 'total' => 0];
            $mapa[$hoja]['total']++;

            if (in_array($r['estado'] ?? '', ['HALLAZGO', 'ERROR_TECNICO'], true)) {
                $mapa[$hoja]['hallazgos']++;
            }
        }

        usort($mapa, fn (array $a, array $b) => strnatcmp($a['hoja'], $b['hoja']));

        return array_values($mapa);
    }

    /** @return list<string> */
    private function terminos(): array
    {
        $limpio = self::normalizar($this->busqueda);

        if ($limpio === '') {
            return [];
        }

        return array_values(array_filter(explode(' ', $limpio), fn ($t) => $t !== ''));
    }

    /** @param  array<string, mixed>  $r */
    private function textoBuscable(array $r): string
    {
        return self::normalizar(implode(' ', [
            (string) ($r['regla'] ?? ''),
            (string) ($r['mensaje'] ?? ''),
            (string) ($r['hoja'] ?? ''),
            (string) ($r['expresion1'] ?? ''),
            (string) ($r['expresion2'] ?? ''),
            implode(' ', (array) ($r['celdas'] ?? [])),
            (string) ($r['evidencia'] ?? ''),
        ]));
    }

    /** Minúsculas sin tildes: «Código» y «codigo» son lo mismo al buscar. */
    public static function normalizar(string $texto): string
    {
        $minusculas = mb_strtolower($texto, 'UTF-8');

        $sinTildes = strtr($minusculas, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ]);

        return trim(preg_replace('/\s+/', ' ', $sinTildes) ?? '');
    }

    /**
     * @param  array<int|string, mixed>  $valores
     * @param  list<string>  $permitidos
     * @return list<string>
     */
    private static function limpiar(array $valores, array $permitidos): array
    {
        return array_values(array_filter(
            array_map('strval', $valores),
            fn (string $v) => in_array($v, $permitidos, true),
        ));
    }

    /** @return list<string> */
    private function estadosOrdenados(): array
    {
        return self::ordenar($this->estados);
    }

    /**
     * @param  list<string>  $valores
     * @return list<string>
     */
    private static function ordenar(array $valores): array
    {
        $copia = $valores;
        sort($copia);

        return $copia;
    }
}
