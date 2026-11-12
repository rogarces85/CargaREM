<?php

namespace Tests\Feature;

use App\Models\CatalogoSnapshot;
use App\Models\Envio;
use App\Models\EnvioArchivo;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Serie;
use App\Models\Servicio;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Rendimiento y configuración de registros (T-F6-06, T-F6-08).
 */
class RendimientoTest extends TestCase
{
    use RefreshDatabase;

    public function test_los_canales_de_registro_tienen_retencion(): void
    {
        foreach (['validacion', 'seguridad', 'correo'] as $canal) {
            $config = config("logging.channels.{$canal}");

            $this->assertNotNull($config, "Falta el canal {$canal}");
            $this->assertSame('daily', $config['driver'], "El canal {$canal} debe rotar por día");
            $this->assertSame(90, $config['days'], "El canal {$canal} debe retener 90 días");
        }
    }

    public function test_los_registros_no_guardan_contenido_de_celdas(): void
    {
        // Se revisa el código: ningún Log:: debe recibir el valor de una celda
        // ni el texto de una observación. Solo identificadores y códigos.
        $sospechosos = [];

        $carpetas = [app_path('Services'), app_path('Validacion'), app_path('Http')];

        foreach ($carpetas as $carpeta) {
            if (! is_dir($carpeta)) {
                continue;
            }

            $archivos = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($carpeta));

            foreach ($archivos as $archivo) {
                if (! $archivo->isFile() || $archivo->getExtension() !== 'php') {
                    continue;
                }

                $codigo = file_get_contents($archivo->getPathname());

                foreach (explode("\n", $codigo) as $numero => $linea) {
                    if (! preg_match('/Log::|logger\(/', $linea)) {
                        continue;
                    }

                    if (preg_match('/\$valor\[\'valor\'\]|\$observacion->texto|\$hallazgo->mensaje|\$texto\b/', $linea)) {
                        $sospechosos[] = $archivo->getFilename().':'.($numero + 1);
                    }
                }
            }
        }

        $this->assertSame([], $sospechosos, 'Estos registros incluyen contenido de datos: '.implode(', ', $sospechosos));
    }

    public function test_la_bandeja_responde_rapido_con_cien_envios(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');

        $servicio = Servicio::create(['codigo' => '23', 'nombre' => 'Osorno']);
        Periodo::create(['anio' => 2026, 'mes' => 9, 'estado' => Periodo::ABIERTO]);
        $snapshot = CatalogoSnapshot::create(['origen' => 'prueba', 'activo' => true]);

        $revisor = User::factory()->create(['activo' => true, 'servicio_id' => $servicio->id]);
        $revisor->assignRole('revisor');

        $encargado = User::factory()->create(['activo' => true]);
        $encargado->assignRole('encargado');

        $serie = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);

        // 100 envíos en revisión, cada uno con su archivo y sus hallazgos.
        for ($i = 1; $i <= 100; $i++) {
            $establecimiento = Establecimiento::create([
                'codigo' => '8000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'nombre' => "Establecimiento {$i}",
                'servicio_id' => $servicio->id,
            ]);

            $establecimiento->series()->attach($serie->id, ['obligatoria' => true]);

            $envio = Envio::create([
                'establecimiento_id' => $establecimiento->id,
                'anio' => 2026, 'mes' => 9, 'estado' => Envio::EN_REVISION,
                'enviado_por' => $encargado->id, 'fecha_envio' => now()->subDays($i % 20),
            ]);

            $envio->archivos()->create([
                'serie_id' => $serie->id, 'version' => 1, 'es_vigente' => true,
                'nombre_original' => 'x.xlsx', 'ruta' => 'envios/x.xlsx', 'disco' => 'local',
                'sha256' => str_repeat('a', 64), 'tamano_bytes' => 100, 'mime' => 'application/vnd.ms-excel',
                'estado' => EnvioArchivo::VALIDO, 'snapshot_id' => $snapshot->id,
                'subido_por' => $encargado->id,
            ]);
        }

        $inicio = microtime(true);
        $respuesta = $this->actingAs($revisor)->get('/revision');
        $segundos = microtime(true) - $inicio;

        $respuesta->assertOk();
        $respuesta->assertViewHas('envios', fn ($pagina) => $pagina->total() === 100);

        $this->assertLessThan(2.0, $segundos, "La bandeja tardó {$segundos} s con 100 envíos");
    }
}
