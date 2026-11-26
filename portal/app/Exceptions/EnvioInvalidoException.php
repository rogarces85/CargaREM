<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Operación de envío rechazada por una regla de negocio.
 *
 * Códigos APP-ENV-### porque no son errores del archivo sino del contexto
 * (período cerrado, catálogo sin importar, envío inexistente).
 */
class EnvioInvalidoException extends RuntimeException
{
    public function __construct(
        public readonly string $codigo,
        string $mensaje,
    ) {
        parent::__construct($mensaje);
    }

    public static function periodoNoExiste(int $anio, int $mes): self
    {
        return new self('APP-ENV-001', "El período {$mes}/{$anio} no existe. Pida al administrador que lo cree.");
    }

    public static function periodoCerrado(int $anio, int $mes, string $estado): self
    {
        return new self('APP-ENV-002', "El período {$mes}/{$anio} está «{$estado}» y no acepta cargas nuevas.");
    }

    public static function periodoEnRevision(int $anio, int $mes): self
    {
        return new self(
            'APP-ENV-012',
            "El período {$mes}/{$anio} está en revisión: solo se aceptan correcciones de envíos observados."
        );
    }

    public static function envioDuplicado(string $codigoEstablecimiento, int $anio, int $mes): self
    {
        return new self(
            'APP-ENV-003',
            "Ya existe un envío del establecimiento {$codigoEstablecimiento} para {$mes}/{$anio}."
        );
    }

    public static function serieNoAplicable(string $serie): self
    {
        return new self('APP-ENV-005', "La serie {$serie} no está activa en el catálogo.");
    }
public static function transicionNoPermitida(string $desde, string $hacia): self
    {
        return new self(
            'APP-ENV-006',
            "No se puede pasar de «{$desde}» a «{$hacia}». Revise el flujo del envío."
        );
    }

public static function observacionVacia(): self
    {
        return new self('APP-OBS-001', 'La observación no puede estar vacía.');
    }

    public static function observacionLarga(): self
    {
        return new self('APP-OBS-002', 'La observación supera los 2000 caracteres.');
    }

    public static function observacionDeOtroEnvio(): self
    {
        return new self('APP-OBS-003', 'El archivo observado no pertenece a este envío.');
    }
public static function aprobacionConObservaciones(int $abiertas): self
    {
        return new self('APP-ENV-009', "Hay {$abiertas} observación(es) sin resolver: no se puede aprobar.");
    }

    public static function aprobacionConBloqueantes(int $bloqueantes): self
    {
        return new self('APP-ENV-010', "El envío tiene {$bloqueantes} archivo(s) con errores bloqueantes.");
    }

    public static function aprobacionIncompleta(int $faltantes): self
    {
        return new self('APP-ENV-011', "Falta(n) {$faltantes} serie(s) exigida(s) por la matriz: el envío no está completo.");
    }

    public static function reaperturaSinMotivo(): self
    {
        return new self(
            'APP-ENV-013',
            'Para reabrir un envío hay que explicar por qué: escriba al menos 10 caracteres.'
        );
    }

    public static function reaperturaImposible(string $estado): self
    {
        return new self(
            'APP-ENV-014',
            "Solo se reabre un envío aprobado. Este está en «{$estado}»: si hay algo que corregir, obsérvelo."
        );
    }

    public static function reenvioSinMotivo(): self
    {
        return new self(
            'APP-ENV-015',
            'Para pedir una nueva subida hay que explicar qué está mal: escriba al menos 10 caracteres.'
        );
    }

    public static function reenvioSinAlcance(): self
    {
        return new self(
            'APP-ENV-016',
            'Indique qué debe volver a subir: una serie, todas las del envío o todos sus establecimientos en un período.'
        );
    }

    public static function periodoInexistente(int $anio, int $mes): self
    {
        // A diferencia de `periodoNoExiste`, este no invita a crear el período:
        // lo usa la solicitud de nueva subida, que trabaja sobre lo ya cargado.
        return new self('APP-ENV-017', "No hay envíos cargados para el período {$mes}/{$anio} en su alcance.");
    }

    public static function aperturaSinMotivo(int $minimo = 15): self
    {
        return new self(
            'APP-APL-001',
            "La solicitud de apertura necesita un motivo de al menos {$minimo} caracteres."
        );
    }

    public static function aperturaYaResuelta(string $estado): self
    {
        return new self(
            'APP-APL-002',
            "Esta solicitud ya está «{$estado}»: no se puede volver a resolver."
        );
    }

    public static function aperturaSinPermisoDeResolucion(): self
    {
        return new self('APP-APL-003', 'Solo el administrador puede aprobar o rechazar una apertura de plazo.');
    }
}