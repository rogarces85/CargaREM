<?php

namespace App\Support;

/**
 * Cómo se llama cada rol en la interfaz (RF-049, D-041).
 *
 * La clave interna no cambia —`revisor` sigue siéndolo en permisos, tests y
 * base— pero nadie fuera del código la ve: en pantalla el rol se llama
 * **Equipo REM**. El mapeo vive aquí y no en las vistas para que un cambio de
 * nombre se haga en un lugar.
 */
final class RolUi
{
    public const ETIQUETAS = [
        'encargado' => 'Encargado de establecimiento',
        'revisor'   => 'Equipo REM',
        'admin'     => 'Administrador',
    ];

    /** Descripción corta, para la ayuda de un formulario. */
    public const DESCRIPCIONES = [
        'encargado' => 'Sube y corrige la estadística de los establecimientos que tenga asignados.',
        'revisor'   => 'Revisa, observa y aprueba la estadística de los establecimientos que tenga asignados.',
        'admin'     => 'Gestiona usuarios, asignaciones, catálogos, períodos y aperturas de plazo.',
    ];

    public static function etiqueta(?string $rol): string
    {
        if ($rol === null || $rol === '') {
            return 'Sin rol';
        }

        return self::ETIQUETAS[$rol] ?? ucfirst(str_replace('_', ' ', $rol));
    }

    public static function descripcion(?string $rol): string
    {
        return self::DESCRIPCIONES[$rol ?? ''] ?? '';
    }

    /**
     * Etiquetas listas para un `<select>`: clave interna => texto visible.
     *
     * @param  list<string>  $roles
     * @return array<string, string>
     */
    public static function opciones(array $roles): array
    {
        $opciones = [];

        foreach ($roles as $rol) {
            $opciones[$rol] = self::etiqueta($rol);
        }

        return $opciones;
    }
}
