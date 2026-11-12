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

    public static function envioDuplicado(string $codigoEstablecimiento, int $anio, int $mes): self
    {
        return new self(
            'APP-ENV-003',
            "Ya existe un envío del establecimiento {$codigoEstablecimiento} para {$mes}/{$anio}."
        );
    }

    public static function sinCatalogo(): self
    {
        return new self(
            'APP-ENV-004',
            'No hay catálogo activo. Sin catálogo no se puede validar: pida al administrador que importe uno.'
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

    public static function motivoRequerido(string $accion): self
    {
        return new self('APP-ENV-007', "Para {$accion} hace falta un motivo: queda en la bitácora.");
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
}