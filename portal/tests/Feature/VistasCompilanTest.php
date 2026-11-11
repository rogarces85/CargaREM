<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Ninguna vista puede tener un error de sintaxis (T-F2-11).
 *
 * Una vista rota no falla hasta que alguien la abre, y entonces falla en
 * producción. Este test compila todas y valida el PHP resultante.
 *
 * Encontró un caso real: `@php(...)` en línea compila en algunas versiones a
 * un `<?php` sin cerrar, y el resto del archivo queda dentro de PHP.
 */
class VistasCompilanTest extends TestCase
{
    public function test_todas_las_vistas_compilan_a_php_valido(): void
    {
        $vistas = glob(resource_path('views/**/*.blade.php'));
        $vistas = array_merge($vistas, glob(resource_path('views/*.blade.php')));

        $this->assertNotEmpty($vistas, 'No se encontraron vistas');

        $errores = [];

        foreach (array_unique($vistas) as $ruta) {
            $compilado = Blade::compileString(file_get_contents($ruta));

            try {
                // TOKEN_PARSE hace que token_get_all lance ParseError si el
                // código no es PHP válido.
                token_get_all($compilado, TOKEN_PARSE);
            } catch (\ParseError $e) {
                $errores[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $ruta).': '.$e->getMessage();
            }
        }

        $this->assertSame([], $errores, "Vistas con error de sintaxis:\n".implode("\n", $errores));
    }
}
