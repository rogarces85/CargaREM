<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

/**
 * Revisa y corrige la configuración de producción (T-F6-05).
 *
 * No cambia el `.env` por su cuenta: avisa y, si se le pide, borra los
 * archivos que nunca deben estar en `public/` — volcados de `phpinfo`,
 * `.env` de ejemplo, scripts sueltos.
 */
class Endurecer extends Command
{
    protected $signature = 'rem:endurecer {--limpiar : Borra los archivos peligrosos de public/}';

    protected $description = 'Revisa la configuración de seguridad y limpia public/ de archivos de diagnóstico.';

    /** Archivos que jamás deben servirse desde public/. */
    private const PELIGROSOS = [
        'info.php', 'phpinfo.php', '.env', '.env.example', '.env.bak',
        'composer.json', 'composer.lock', 'artisan',
    ];

    public function handle(): int
    {
        $this->line('Configuración de la aplicación');
        $this->table(['Variable', 'Valor', 'Esperado en producción'], [
            ['APP_ENV', config('app.env'), 'production'],
            ['APP_DEBUG', config('app.debug') ? 'true' : 'false', 'false'],
            ['APP_URL', config('app.url'), 'URL pública con https'],
            ['MAIL_MAILER', config('mail.default'), 'smtp'],
            ['LOG_LEVEL', config('logging.channels.single.level') ?? '—', 'warning o superior'],
        ]);

        $problemas = [];

        if (config('app.debug')) {
            $problemas[] = 'APP_DEBUG está activo: en producción muestra el código y las credenciales en los errores.';
        }

        if (config('app.env') !== 'production') {
            $problemas[] = 'APP_ENV no es production.';
        }

        if (blank(config('rem.paquete.hmac_key'))) {
            $problemas[] = 'PAQUETE_HMAC_KEY está vacía: los paquetes se entregan sin firma.';
        }

        $this->newLine();
        $this->line('Archivos en public/ que no deben estar');

        $encontrados = $this->archivosPeligrosos();

        if ($encontrados === []) {
            $this->info('  Ninguno.');
        } else {
            foreach ($encontrados as $ruta) {
                $this->warn('  '.str_replace(public_path().DIRECTORY_SEPARATOR, '', $ruta));
            }

            if ($this->option('limpiar')) {
                foreach ($encontrados as $ruta) {
                    @unlink($ruta);
                }

                $this->info('  Borrados.');
            } else {
                $problemas[] = 'Hay archivos de diagnóstico en public/. Ejecute con --limpiar.';
            }
        }

        $this->newLine();

        if ($problemas === []) {
            $this->info('Sin problemas detectados.');

            return self::SUCCESS;
        }

        $this->error('Pendientes antes de publicar:');
        foreach ($problemas as $problema) {
            $this->line('  · '.$problema);
        }

        return self::FAILURE;
    }

    /** @return list<string> */
    private function archivosPeligrosos(): array
    {
        $encontrados = [];

        foreach (self::PELIGROSOS as $nombre) {
            $ruta = public_path($nombre);

            if (is_file($ruta)) {
                $encontrados[] = $ruta;
            }
        }

        // Cualquier archivo que se llame como un volcado de información.
        $finder = Finder::create()
            ->files()
            ->in(public_path())
            ->depth('== 0')
            ->name('/^(php)?info.*\.php$/i')
            ->name('/^(test|debug|prueba).*\.php$/i');

        foreach ($finder as $archivo) {
            $encontrados[] = $archivo->getRealPath();
        }

        return array_values(array_unique($encontrados));
    }
}
