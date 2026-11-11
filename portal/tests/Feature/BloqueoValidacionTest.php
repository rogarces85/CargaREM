<?php

namespace Tests\Feature;

use App\Exceptions\ValidacionOcupadaException;
use App\Validacion\BloqueoValidacion;
use Tests\TestCase;

/**
 * Semáforo de validaciones concurrentes (T-F2-29).
 */
class BloqueoValidacionTest extends TestCase
{
    public function test_ejecuta_la_tarea_y_libera_el_hueco(): void
    {
        $bloqueo = new BloqueoValidacion(slots: 2, esperaSegundos: 1);

        $resultado = $bloqueo->conSlot(fn () => 'hecho');

        $this->assertSame('hecho', $resultado);
        $this->assertSame(0, $bloqueo->ocupados());
    }

    public function test_admite_dos_validaciones_a_la_vez(): void
    {
        $bloqueo = new BloqueoValidacion(slots: 2, esperaSegundos: 0);

        // Anidados: el de fuera ocupa un hueco y el de dentro el otro.
        $resultado = $bloqueo->conSlot(
            fn () => $bloqueo->conSlot(fn () => 'dos')
        );

        $this->assertSame('dos', $resultado);
    }

    public function test_la_tercera_espera_y_luego_falla(): void
    {
        $bloqueo = new BloqueoValidacion(slots: 2, esperaSegundos: 0);

        // Ocupa los dos huecos a mano.
        $this->assertSame(0, $bloqueo->ocupados());

        $bloqueo->conSlot(function () use ($bloqueo) {
            $bloqueo->conSlot(function () use ($bloqueo) {
                // Aquí dentro los dos huecos están ocupados.
                try {
                    $bloqueo->conSlot(fn () => 'no debería');
                    $this->fail('Debería rechazar la tercera validación');
                } catch (ValidacionOcupadaException $e) {
                    $this->assertSame('APP-ENV-008', $e->codigo);
                    $this->assertStringContainsString('2 validaciones', $e->getMessage());
                }
            });
        });
    }

    public function test_libera_el_hueco_aunque_la_tarea_falle(): void
    {
        $bloqueo = new BloqueoValidacion(slots: 1, esperaSegundos: 0);

        try {
            $bloqueo->conSlot(function () {
                throw new \RuntimeException('la validación explotó');
            });
        } catch (\RuntimeException) {
            // esperado
        }

        // El hueco quedó libre: otra validación entra sin esperar.
        $this->assertSame('segunda', $bloqueo->conSlot(fn () => 'segunda'));
        $this->assertSame(0, $bloqueo->ocupados());
    }

    public function test_cuenta_los_huecos_ocupados(): void
    {
        $bloqueo = new BloqueoValidacion(slots: 3, esperaSegundos: 0);

        $this->assertSame(0, $bloqueo->ocupados());

        $bloqueo->conSlot(function () use ($bloqueo) {
            $this->assertSame(1, $bloqueo->ocupados());
        });
    }

    public function test_toma_la_configuracion_del_portal(): void
    {
        config(['rem.validacion.concurrentes' => 2]);

        $bloqueo = app(BloqueoValidacion::class);

        $this->assertSame(0, $bloqueo->ocupados());
    }
}
