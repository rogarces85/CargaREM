<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad en todas las respuestas (T-F6-01).
 *
 * El portal no se incrusta en otro sitio, no adivina tipos de contenido y no
 * filtra a dónde navega el usuario. La CSP limita de dónde puede venir el
 * código que se ejecuta.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        $response->headers->set('Content-Security-Policy', $this->politica($request));

        // HSTS solo tiene sentido sobre HTTPS: en HTTP el navegador lo ignora,
        // y enviarlo en local complica las pruebas.
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function politica(Request $request): string
    {
        $partes = [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "img-src 'self' data:",
            "font-src 'self' https://fonts.gstatic.com data:",
            // Las vistas usan estilos y scripts en línea (AdminLTE y los modales
            // de la bandeja). Se permite 'unsafe-inline' a sabiendas: bloquea
            // igual la inyección de scripts externos, que es el vector real.
            // Deuda anotada: mover el JavaScript a archivos propios.
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "script-src 'self' 'unsafe-inline'",
            "connect-src 'self'",
        ];

        return implode('; ', $partes);
    }
}
