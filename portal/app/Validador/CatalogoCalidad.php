<?php

namespace App\Validador;

/**
 * El conjunto de reglas cargado desde el archivo de configuración.
 *
 * El archivo es el insumo de la Unidad de Estadísticas; la versión viaja con
 * cada ejecución para poder repetir una revisión con el mismo criterio.
 */
final class CatalogoCalidad
{
    /**
     * @param  list<ReglaCalidad>  $reglas
     */
    public function __construct(
        public readonly string $version,
        public readonly string $nombre,
        public readonly array $reglas,
        public readonly string $ruta,
    ) {
    }

    public static function desdeArchivo(?string $ruta = null): self
    {
        $ruta ??= base_path('config/validador/reglas.json');

        if (! is_readable($ruta)) {
            throw new \RuntimeException(
                'No se encontró el archivo de reglas del Validador en ' . $ruta . '.'
            );
        }

        $contenido = json_decode((string) file_get_contents($ruta), true);

        if (! is_array($contenido)) {
            throw new \RuntimeException('El archivo de reglas del Validador no es un JSON válido.');
        }

        $reglas = [];

        foreach (($contenido['reglas'] ?? []) as $indice => $datos) {
            if (! is_array($datos)) {
                throw new \RuntimeException('Cada regla del Validador debe ser un objeto.');
            }

            $regla = ReglaCalidad::desdeArreglo($datos, (int) $indice);

            // Se comprueba el tipo al cargar y no al ejecutar: así el error
            // aparece antes de leer el Excel, con el nombre de la regla.
            if (! in_array($regla->tipo, ReglaCalidad::TIPOS, true)) {
                throw new \RuntimeException(
                    "La regla {$regla->id} usa un tipo desconocido: «{$regla->tipo}». " .
                    'Tipos admitidos: ' . implode(', ', ReglaCalidad::TIPOS) . '.'
                );
            }

            $reglas[] = $regla;
        }

        if ($reglas === []) {
            throw new \RuntimeException('El archivo de reglas del Validador no declara ninguna regla.');
        }

        return new self(
            version: (string) ($contenido['version'] ?? '0'),
            nombre: (string) ($contenido['nombre'] ?? 'Validador'),
            reglas: $reglas,
            ruta: $ruta,
        );
    }
}
