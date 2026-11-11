<?php

namespace App\Validacion;

use App\Exceptions\ValidacionOcupadaException;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

/**
 * Semáforo de validaciones concurrentes (T-F2-29).
 *
 * El servidor admite dos validaciones a la vez: cada una puede consumir
 * cientos de megas al abrir un Excel. Sin límite, tres envíos simultáneos
 * tumban el proceso por memoria, y el mensaje que ve el usuario no dice nada.
 *
 * Se implementa como N candados numerados en el almacén de caché: el primero
 * que se libera lo toma el siguiente. No necesita Redis ni tabla nueva.
 */
class BloqueoValidacion
{
    public function __construct(
        private readonly ?int $slots = null,
        private readonly ?int $esperaSegundos = null,
    ) {
    }

    /**
     * Ejecuta la tarea ocupando uno de los huecos disponibles.
     */
    public function conSlot(callable $tarea): mixed
    {
        $lock = $this->adquirir();

        try {
            return $tarea();
        } finally {
            // Se libera siempre: si la validación lanza, el hueco no puede
            // quedarse ocupado hasta que expire.
            $lock->release();
        }
    }

    /** Cuántos huecos están ocupados ahora mismo. */
    public function ocupados(): int
    {
        $ocupados = 0;

        foreach (range(1, $this->slots()) as $i) {
            $lock = Cache::lock($this->clave($i), $this->ttl());

            if (! $lock->get()) {
                $ocupados++;
            } else {
                $lock->release();
            }
        }

        return $ocupados;
    }

    private function adquirir(): Lock
    {
        $espera = $this->espera();
        $limite = microtime(true) + $espera;

        do {
            foreach (range(1, $this->slots()) as $i) {
                $lock = Cache::lock($this->clave($i), $this->ttl());

                if ($lock->get()) {
                    return $lock;
                }
            }

            if ($espera <= 0) {
                break;
            }

            usleep(200_000);
        } while (microtime(true) < $limite);

        throw ValidacionOcupadaException::sinSlot($this->slots(), $espera);
    }

    private function clave(int $slot): string
    {
        return "validacion:slot:{$slot}";
    }

    private function slots(): int
    {
        return max(1, $this->slots ?? (int) config('rem.validacion.concurrentes', 2));
    }

    private function espera(): int
    {
        return max(0, $this->esperaSegundos ?? (int) config('rem.validacion.espera_lock_segundos', 10));
    }

    private function ttl(): int
    {
        return max(30, (int) config('rem.validacion.timeout_segundos', 90));
    }
}
