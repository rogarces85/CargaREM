<?php

namespace App\Support;

/**
 * Cómo se presenta cada estado del dominio.
 *
 * El dominio habla de `en_revision`, `con_errores`, `sin_archivo`. La interfaz
 * necesita una etiqueta legible y un tono de color. Ese mapeo vive aquí y no en
 * las vistas, por tres razones:
 *
 *   - un estado nuevo se agrega en un lugar, no en cinco tablas;
 *   - los tonos son un vocabulario cerrado (`ok`, `info`, `warn`, `danger`,
 *     `idle`, `none`), así que ninguna vista inventa un color;
 *   - la etiqueta se puede probar sin renderizar Blade.
 *
 * Los tonos corresponden a los tokens `--status-*` de `resources/css/app.css`.
 * `none` es deliberado: un estado que no aplica no debe teñir la celda.
 */
class EstadoUi
{
    /** Etiqueta y tono por valor de estado. */
    public const MAPA = [
        // Envío
        'recibido'     => ['etiqueta' => 'Recibido', 'tono' => 'info'],
        'validado'     => ['etiqueta' => 'Validado', 'tono' => 'info'],
        'con_errores'  => ['etiqueta' => 'Con errores', 'tono' => 'danger'],
        'en_revision'  => ['etiqueta' => 'En revisión', 'tono' => 'warn'],
        'observado'    => ['etiqueta' => 'Observado', 'tono' => 'warn'],
        'aprobado'     => ['etiqueta' => 'Aprobado', 'tono' => 'ok'],

        // Archivo
        'subido'       => ['etiqueta' => 'Subido', 'tono' => 'info'],
        'validando'    => ['etiqueta' => 'Validando', 'tono' => 'info'],
        'valido'       => ['etiqueta' => 'Válido', 'tono' => 'ok'],
        'reemplazado'  => ['etiqueta' => 'Reemplazado', 'tono' => 'idle'],

        // Completitud
        'sin_envio'    => ['etiqueta' => 'Sin envío', 'tono' => 'idle'],
        'sin_archivo'  => ['etiqueta' => 'Sin archivo', 'tono' => 'idle'],
        'en_proceso'   => ['etiqueta' => 'En proceso', 'tono' => 'warn'],
        'no_aplica'    => ['etiqueta' => 'No aplica', 'tono' => 'none'],

        // Severidad de validación
        'bloqueante'   => ['etiqueta' => 'Bloqueante', 'tono' => 'danger'],
        'advertencia' => ['etiqueta' => 'Advertencia', 'tono' => 'warn'],

        // Período
        'abierto'      => ['etiqueta' => 'Abierto', 'tono' => 'ok'],
        'cerrado'      => ['etiqueta' => 'Cerrado', 'tono' => 'idle'],
    ];

    /**
     * Devuelve etiqueta y tono de un valor. Si el valor no está en el mapa,
     * muestra el valor crudo con guiones bajos cambiados a espacios y tono
     * neutro: un estado desconocido debe verse, no romper la página.
     *
     * @return array{etiqueta: string, tono: string}
     */
    public static function de(?string $valor): array
    {
        if ($valor === null || $valor === '') {
            return ['etiqueta' => '—', 'tono' => 'none'];
        }

        return self::MAPA[$valor] ?? [
            'etiqueta' => ucfirst(str_replace('_', ' ', $valor)),
            'tono'     => 'idle',
        ];
    }

    /** Solo la etiqueta. */
    public static function etiqueta(?string $valor): string
    {
        return self::de($valor)['etiqueta'];
    }
}
