<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SistemaNotificacion;
use App\Support\RolUi;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avisos que no cuelgan de un envío (v3).
 *
 * El `Notificador` existente responde a «qué le pasa a este envío». Este
 * responde a «qué le pasa a esta persona»: quedó sin establecimientos, le
 * asignaron un reparto, el administrador abrió un plazo. Son avisos de gestión
 * y no cambian el estado de nada.
 *
 * Regla de la casa: un aviso nunca rompe la operación que lo provoca. Si la
 * campana falla, el cambio ya está hecho y se deja constancia en el log.
 */
class Avisar
{
    /**
     * @param  list<User>  $destinatarios
     * @param  array<string, mixed>  $datos
     */
    public function enviar(array $destinatarios, string $clave, array $datos = []): int
    {
        $enviados = 0;

        foreach ($destinatarios as $usuario) {
            if (! $usuario->activo) {
                continue;
            }

            try {
                $usuario->notify(new SistemaNotificacion($clave, $datos));
                $enviados++;
            } catch (Throwable $e) {
                report($e);
                Log::warning('No se pudo registrar el aviso de gestión.', [
                    'clave' => $clave,
                    'para'  => $usuario->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $enviados;
    }

    /**
     * Avisa al administrador de que un usuario quedó sin establecimientos.
     *
     * Se llama al guardar una asignación vacía y al crear un revisor sin
     * reparto: sin este aviso, el síntoma —una bandeja vacía— parece un fallo
     * del sistema y no una configuración pendiente.
     */
    public function sinAsignacion(User $usuario): int
    {
        $admins = User::role('admin')->where('activo', true)->whereKeyNot($usuario->id)->get()->all();

        return $this->enviar($admins, 'sin_asignacion', [
            'usuario' => $usuario->name,
            'usuario_id' => $usuario->id,
            'rol' => RolUi::etiqueta($usuario->getRoleNames()->first()),
        ]);
    }
}
