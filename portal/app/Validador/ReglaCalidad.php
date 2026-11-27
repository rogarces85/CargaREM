<?php

namespace App\Validador;

/**
 * Una regla de calidad tal como viene del catálogo de la Unidad (RF-040).
 *
 * Sustituye al modelo de tipos genéricos que había antes: el catálogo real no
 * declara «hoja_presente» ni «rango», declara comparaciones entre dos
 * expresiones del libro, con tres formas:
 *
 * - **SIMPLE**: un lado del libro contra un literal (`F11 == 0`).
 * - **DOBLE**: dos celdas del libro que deben cuadrar entre sí (`B11 == B21`).
 * - **COMPUESTA**: sumas de celdas, a veces cruzando hojas
 *   (`C19+C20+…+F38 == A05!C89`).
 *
 * El tipo no cambia cómo se evalúa —los tres se reducen a dos expresiones y un
 * operador— pero sí cómo se lee la regla y qué se informa al usuario.
 */
final class ReglaCalidad
{
    /** Tipos del Excel maestro. */
    public const TIPOS = ['SIMPLE', 'DOBLE', 'COMPUESTA'];

    /** Severidades del catálogo. */
    public const ERROR = 'ERROR';
    public const REVISAR = 'REVISAR';
    public const INDICADOR = 'INDICADOR';

    public const SEVERIDADES = [self::ERROR, self::REVISAR, self::INDICADOR];

    public const OPERADORES = ['==', '!=', '>', '<', '>=', '<='];

    /**
     * @param  list<string>|null  $aplicarA  códigos DEIS a los que se limita
     * @param  list<string>|null  $aplicarATipo  tipos de establecimiento
     * @param  list<string>|null  $excluirA  códigos excluidos
     * @param  list<string>|null  $excluirTipo  tipos excluidos
     */
    public function __construct(
        public readonly string $id,
        public readonly string $tipo,
        public readonly string $severidad,
        public readonly string $hoja,
        public readonly string $expresion1,
        public readonly string $operador,
        public readonly string|float $expresion2,
        public readonly ?string $hoja2,
        public readonly string $mensaje,
        public readonly ?string $seccion = null,
        public readonly ?string $detalle = null,
        public readonly ?string $seccion2 = null,
        public readonly ?string $detalle2 = null,
        public readonly ?array $aplicarA = null,
        public readonly ?array $aplicarATipo = null,
        public readonly ?array $excluirA = null,
        public readonly ?array $excluirTipo = null,
        public readonly ?array $condicionPrevia = null,
        public readonly bool $omitirSiCondicionNoCumple = false,
        public readonly bool $omitirSiSinActividad = false,
        public readonly ?string $correccion = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function desdeArreglo(array $datos, int $indice, string $hojaClave): self
    {
        $id = trim((string) ($datos['id'] ?? ''));

        if ($id === '') {
            throw new \RuntimeException("La regla #{$indice} de la hoja {$hojaClave} no tiene «id».");
        }

        $tipo = strtoupper(trim((string) ($datos['tipo'] ?? '')));

        if (! in_array($tipo, self::TIPOS, true)) {
            throw new \RuntimeException(
                "La regla {$id} declara un tipo desconocido: «{$tipo}». Admitidos: " . implode(', ', self::TIPOS) . '.'
            );
        }

        $severidad = strtoupper(trim((string) ($datos['severidad'] ?? '')));

        if (! in_array($severidad, self::SEVERIDADES, true)) {
            throw new \RuntimeException(
                "La regla {$id} declara una severidad desconocida: «{$severidad}». Admitidas: " . implode(', ', self::SEVERIDADES) . '.'
            );
        }

        $operador = trim((string) ($datos['operador'] ?? ''));

        if (! in_array($operador, self::OPERADORES, true)) {
            throw new \RuntimeException(
                "La regla {$id} declara un operador desconocido: «{$operador}». Admitidos: " . implode(' ', self::OPERADORES) . '.'
            );
        }

        $expresion1 = trim((string) ($datos['expresion_1'] ?? ''));

        if ($expresion1 === '') {
            throw new \RuntimeException("La regla {$id} no declara «expresion_1»: no hay nada que comparar.");
        }

        $expresion2 = $datos['expresion_2'] ?? 0;

        // El Excel maestro deja la celda vacía cuando el lado derecho es cero.
        if (is_string($expresion2) && trim($expresion2) === '') {
            $expresion2 = 0;
        }

        $mensaje = trim((string) ($datos['mensaje'] ?? ''));

        if ($mensaje === '') {
            throw new \RuntimeException("La regla {$id} no tiene mensaje: el hallazgo no se podría explicar.");
        }

        $condicion = null;

        if (isset($datos['condicion_previa']) && is_array($datos['condicion_previa'])) {
            $condicion = [
                'expresion' => (string) ($datos['condicion_previa']['expresion'] ?? ''),
                'operador'  => (string) ($datos['condicion_previa']['operador'] ?? '>'),
                'valor'     => (float) ($datos['condicion_previa']['valor'] ?? 0),
            ];
        }

        $correccion = null;

        if (isset($datos['correccion']) && is_array($datos['correccion'])) {
            $correccion = (string) ($datos['correccion']['motivo'] ?? '');
        }

        return new self(
            id: $id,
            tipo: $tipo,
            severidad: $severidad,
            hoja: (string) ($datos['rem_sheet'] ?? $hojaClave),
            expresion1: $expresion1,
            operador: $operador,
            expresion2: $expresion2,
            hoja2: isset($datos['rem_sheet_2']) && $datos['rem_sheet_2'] !== ''
                ? (string) $datos['rem_sheet_2']
                : null,
            mensaje: $mensaje,
            seccion: self::textoONull($datos['seccion'] ?? null),
            detalle: self::textoONull($datos['detalle'] ?? null),
            seccion2: self::textoONull($datos['seccion_2'] ?? null),
            detalle2: self::textoONull($datos['detalle_2'] ?? null),
            aplicarA: self::lista($datos['aplicar_a'] ?? null),
            aplicarATipo: self::lista($datos['aplicar_a_tipo'] ?? null),
            excluirA: self::lista($datos['excluir_a'] ?? null),
            excluirTipo: self::lista($datos['excluir_tipo'] ?? null),
            condicionPrevia: $condicion,
            omitirSiCondicionNoCumple: (bool) ($datos['omitir_si_condicion_no_cumple'] ?? false),
            omitirSiSinActividad: (bool) ($datos['omitir_si_sin_actividad'] ?? false),
            correccion: $correccion !== '' ? $correccion : null,
        );
    }

    public function esError(): bool
    {
        return $this->severidad === self::ERROR;
    }

    /** Hojas que la regla necesita leer. */
    public function hojasRequeridas(): array
    {
        return $this->hoja2 !== null && $this->hoja2 !== $this->hoja
            ? [$this->hoja, $this->hoja2]
            : [$this->hoja];
    }

    /** El lado derecho como texto, para mostrar. */
    public function expresion2ComoTexto(): string
    {
        return is_float($this->expresion2)
            ? rtrim(rtrim(number_format($this->expresion2, 4, '.', ''), '0'), '.') ?: '0'
            : $this->expresion2;
    }

    private static function textoONull(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    /**
     * El catálogo escribe los alcances como texto separado por comas; en JSON
     * pueden venir ya como arreglo. Se aceptan las dos formas.
     *
     * @return list<string>|null
     */
    private static function lista(mixed $valor): ?array
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_array($valor)) {
            $elementos = array_map(fn ($v) => trim((string) $v), $valor);
        } else {
            $elementos = array_map('trim', explode(',', (string) $valor));
        }

        $elementos = array_values(array_filter($elementos, fn (string $v) => $v !== ''));

        return $elementos === [] ? null : $elementos;
    }
}
