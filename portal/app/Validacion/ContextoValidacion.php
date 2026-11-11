<?php

namespace App\Validacion;

use App\Models\Envio;
use App\Models\EnvioArchivo;
use App\Models\Plantilla;
use App\Excel\DatosArchivo;
use App\Excel\LectorDatos;
use App\Models\Serie;

/**
 * Todo lo que una regla necesita para decidir, sin tocar la base de datos.
 */
final class ContextoValidacion
{
    private ?DatosArchivo $datos = null;

    public function __construct(
        public readonly Envio $envio,
        public readonly EnvioArchivo $archivo,
        public readonly Serie $serie,
        public readonly string $rutaAbsoluta,
        public readonly ?Plantilla $plantilla = null,
        public readonly int $snapshotId = 0,
    ) {
    }

    /**
     * Datos de las celdas, leídos una sola vez por validación.
     *
     * V6 a V10 necesitan lo mismo; volver a abrir el libro por cada regla
     * costaría segundos por archivo.
     */
    public function datos(): DatosArchivo
    {
        if ($this->datos === null) {
            $this->datos = app(LectorDatos::class)->leer($this);
        }

        return $this->datos;
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