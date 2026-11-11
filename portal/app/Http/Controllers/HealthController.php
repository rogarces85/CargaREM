<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Comprobación de salud del portal.
 *
 * Devuelve solo el estado, nunca el mensaje de la excepción: la ruta es
 * pública y un mensaje de error de base de datos revela estructura.
 * El detalle queda en el log del servidor.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'db'      => $this->checkDatabase(),
            'storage' => $this->checkStorage(),
        ];

        $ok = ! in_array('fail', $checks, true);

        return response()->json([
            'status'  => $ok ? 'ok' : 'degraded',
            'checks'  => $checks,
            'entorno' => app()->environment(),
            'version' => app()->version(),
            'hora'    => now()->toIso8601String(),
        ], $ok ? 200 : 503);
    }

    private function checkDatabase(): string
    {
        try {
            DB::connection()->select('select 1');

            return 'ok';
        } catch (Throwable $e) {
            report($e);

            return 'fail';
        }
    }

    private function checkStorage(): string
    {
        try {
            $sonda = 'health/'.uniqid('probe_', true).'.txt';

            Storage::disk('local')->put($sonda, 'ok');
            $leido = Storage::disk('local')->get($sonda);
            Storage::disk('local')->delete($sonda);

            return $leido === 'ok' ? 'ok' : 'fail';
        } catch (Throwable $e) {
            report($e);

            return 'fail';
        }
    }
}
