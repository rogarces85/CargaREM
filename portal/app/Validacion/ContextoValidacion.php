<?php

namespace App\Validacion;

use App\Models\Envio;
use App\Models\EnvioArchivo;
use App\Models\Plantilla;
use App\Models\Serie;

/**
 * Todo lo que una regla necesita para decidir, sin tocar la base de datos.
 */
final class ContextoValidacion
{
    public function __construct(
        public readonly Envio $envio,
        public readonly EnvioArchivo $archivo,
        public readonly Serie $serie,
        public readonly string $rutaAbsoluta,
        public readonly ?Plantilla $plantilla = null,
        public readonly int $snapshotId = 0,
    ) {
    }

    public function codigoEstablecimiento(): string
    {
        return (string) $this->envio->establecimiento->codigo;
    }

    public function anio(): int
    {
        return (int) $this->envio->anio;
    }

    public function mes(): int
    {
        return (int) $this->envio->mes;
    }
}