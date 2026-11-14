<?php

namespace Database\Seeders;

use App\Services\ImportadorCatalogo;
use Illuminate\Database\Seeder;

/**
 * Carga el catálogo de validación desde los CSV del Minsal.
 *
 * En v2.0 el catálogo se siembra: no hay importador en la interfaz ni
 * snapshots. Si `REM_CATALOGO_DIR` no existe, el portal arranca con el juego
 * mínimo de L1 y lo avisa; no borra nada.
 */
class CatalogoSeeder extends Seeder
{
    public function run(ImportadorCatalogo $importador): void
    {
        $directorio = (string) config('rem.catalogo.ruta');

        if ($directorio === '' || ! $importador->disponible($directorio)) {
            $this->command?->warn(
                'Catálogo no sembrado: falta el directorio o algún CSV en «'.$directorio.'». '
                .'Defina REM_CATALOGO_DIR apuntando a la exportación de 01-TOOLS/rem-export.'
            );

            return;
        }

        $resumen = $importador->cargar($directorio);

        $this->command?->info(sprintf(
            'Catálogo sembrado: %d series, %d establecimientos, %d plantillas, %d hojas, %d filas de diccionario.',
            $resumen['series'],
            $resumen['establecimientos'],
            $resumen['plantillas'],
            $resumen['hojas'],
            $resumen['diccionario']
        ));
    }
}
