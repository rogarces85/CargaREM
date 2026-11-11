<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_responde_ok_cuando_base_y_almacen_funcionan(): void
    {
        $response = $this->get('/health');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
            'checks' => [
                'db'      => 'ok',
                'storage' => 'ok',
            ],
        ]);
    }

    public function test_devuelve_503_sin_filtrar_el_detalle_cuando_la_base_falla(): void
    {
        // Conexión deliberadamente rota: puerto cerrado. La ruta es pública,
        // así que la respuesta no puede revelar el mensaje del driver.
        config([
            'database.connections.rota' => [
                'driver'    => 'mysql',
                'host'      => '127.0.0.1',
                'port'      => '1',
                'database'  => 'no_existe',
                'username'  => 'no_existe',
                'password'  => 'no_existe',
                'charset'   => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix'    => '',
                'strict'    => true,
            ],
            'database.default' => 'rota',
        ]);
        DB::purge('rota');

        $response = $this->get('/health');

        $response->assertStatus(503);
        $response->assertJsonPath('status', 'degraded');
        $response->assertJsonPath('checks.db', 'fail');
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('Access denied', $response->getContent());
    }
}
