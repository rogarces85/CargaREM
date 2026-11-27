<?php

namespace App\Validador;

/**
 * Presentación de un resultado del Validador (RF-040.5).
 *
 * Vive fuera del motor a propósito: el motor no sabe cómo se muestran sus
 * resultados. Aquí se traduce el vocabulario del dominio al de la pantalla, y
 * nada más: no decide estados ni evalúa reglas.
 */
final class DetalleResultado
{
    /** Lectura en palabras de la comparación que hace la regla. */
    public const OPERADORES = [
        '==' => 'debe ser igual a',
        '!=' => 'debe ser distinto de',
        '>'  => 'debe ser mayor que',
        '<'  => 'debe ser menor que',
        '>=' => 'debe ser mayor o igual a',
        '<=' => 'debe ser menor o igual a',
    ];

    /**
     * Segmenta un mensaje de regla en sus partes tipográficas.
     *
     * Todos los mensajes del catálogo siguen el patrón
     * `REM <hoja> | <secciones> | <detalle>`. Las reglas compuestas encadenan
     * dos de esas partes (una por cada hoja), así que se devuelve una lista.
     *
     * @return list<array{rem: string, secciones: string, detalle: string}>
     */
    public static function segmentar(string $mensaje): array
    {
        $trozos = preg_split('/(?=REM\s+\S+\s*\|)/u', $mensaje, -1, PREG_SPLIT_NO_EMPTY);

        if ($trozos === false || $trozos === []) {
            return [['rem' => '', 'secciones' => '', 'detalle' => $mensaje]];
        }

        $partes = [];

        foreach ($trozos as $trozo) {
            $trozo = trim($trozo);

            if ($trozo === '') {
                continue;
            }

            // Sin patrón reconocible el trozo completo va al detalle: el
            // mensaje nunca se pierde por no encajar en el formato.
            if (! preg_match('/^(REM\s+\S+)\s*\|\s*([^|]+?)\s*\|\s*(.*)$/su', $trozo, $m)) {
                $partes[] = ['rem' => '', 'secciones' => '', 'detalle' => $trozo];

                continue;
            }

            $partes[] = ['rem' => $m[1], 'secciones' => $m[2], 'detalle' => $m[3]];
        }

        return $partes === []
            ? [['rem' => '', 'secciones' => '', 'detalle' => $mensaje]]
            : $partes;
    }

    public static function leerOperador(string $operador): string
    {
        return self::OPERADORES[$operador] ?? $operador;
    }

    /** «—» y no «0» cuando no hay valor: el cero es un dato, la ausencia no. */
    public static function numero(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        $numero = (float) $valor;

        if (abs($numero - round($numero)) < 0.0001) {
            return number_format($numero, 0, ',', '.');
        }

        return number_format($numero, 2, ',', '.');
    }

    /** Diferencia con su signo: «+5» dice más que «5». */
    public static function diferencia(?float $valor): string
    {
        if ($valor === null) {
            return '—';
        }

        $texto = self::numero($valor);

        return $valor > 0 ? '+' . $texto : $texto;
    }
}
