<?php

namespace App\Validador;

/**
 * Adaptador entre el libro Excel y las reglas (RF-040).
 *
 * Contrato mínimo que el dominio necesita: leer una celda y sumar un rango.
 * El motor no sabe de PhpSpreadsheet; así se puede probar con un libro
 * simulado en memoria y el mismo motor sirve si un día cambia el lector.
 *
 * **Nunca ejecuta macros.** Abre con `setReadDataOnly(true)`, que descarta
 * fórmulas y estilos. Los `.xlsm` se leen por sus valores, no por su código
 * (Constitución, principio 19).
 */
interface LectorLibro
{
    /** Valor de la celda, o `null` si está vacía o la hoja no existe. */
    public function celda(string $hoja, string $ref): mixed;

    /** Suma numérica de un rango, con la tolerancia de nombres de hoja. */
    public function sumaRango(string $hoja, string $desde, string $hasta): float;

    /** @return list<string> */
    public function hojas(): array;

    /** ¿El libro trae esa hoja? La comparación ignora mayúsculas y espacios. */
    public function tieneHoja(string $hoja): bool;
}
