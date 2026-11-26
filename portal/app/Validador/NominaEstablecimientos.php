<?php

namespace App\Validador;

use RuntimeException;

/**
 * Nómina de establecimientos del Servicio para el Validador (RF-040).
 *
 * El catálogo lo genera la herramienta de la Unidad y trae el **tipo** de cada
 * recinto. El tipo es obligatorio para evaluar: sin él, las reglas dirigidas a
 * un tipo quedarían fuera de alcance en silencio y el resultado parecería
 * limpio.
 *
 * Esta nómina convive con la tabla `establecimientos` del portal y no la
 * sustituye: aquella es el catálogo operativo del flujo de envío; esta es la
 * que el Validador necesita para decidir el alcance de una regla, con los
 * tipos tal como los nombra la Unidad (HOSPITAL, CESFAM, POSTA, SAPU, SUR,
 * SALUD MENTAL, DIRECCION, MOVIL, OTROS, CECOSF).
 */
final class NominaEstablecimientos
{
    /**
     * @param  array<string, array{codigo: string, nombre: string, comuna: string, tipo: string, activo: bool, alias: list<string>}>  $porCodigo
     */
    private function __construct(
        public readonly string $version,
        public readonly string $servicio,
        private readonly array $porCodigo,
    ) {
    }

    public static function desdeArchivo(?string $ruta = null): self
    {
        $ruta ??= config_path('validador/establecimientos.json');

        if (! is_readable($ruta)) {
            throw new RuntimeException('No se encontró la nómina de establecimientos del Validador en ' . $ruta . '.');
        }

        $datos = json_decode((string) file_get_contents($ruta), true);

        if (! is_array($datos) || ! isset($datos['establecimientos'])) {
            throw new RuntimeException('La nómina de establecimientos del Validador no tiene el formato esperado.');
        }

        $porCodigo = [];

        foreach ($datos['establecimientos'] as $fila) {
            $codigo = trim((string) ($fila['codigo'] ?? ''));

            if ($codigo === '') {
                continue;
            }

            $porCodigo[$codigo] = [
                'codigo' => $codigo,
                'nombre' => (string) ($fila['nombre'] ?? ''),
                'comuna' => (string) ($fila['comuna'] ?? ''),
                'tipo'   => (string) ($fila['tipo'] ?? ''),
                'activo' => (bool) ($fila['activo'] ?? true),
                'alias'  => array_map('strval', (array) ($fila['alias'] ?? [])),
            ];
        }

        return new self(
            version: (string) ($datos['version'] ?? 'sin versión'),
            servicio: (string) ($datos['servicioDeSalud'] ?? 'Servicio de Salud Osorno'),
            porCodigo: $porCodigo,
        );
    }

    /**
     * @return array{codigo: string, nombre: string, comuna: string, tipo: string, activo: bool, alias: list<string>}|null
     */
    public function buscar(string $codigo): ?array
    {
        return $this->porCodigo[trim($codigo)] ?? null;
    }

    /** @return list<array<string, mixed>> */
    public function todos(): array
    {
        return array_values($this->porCodigo);
    }

    /** Nómina como la espera `NombreEstablecimiento::verificar()`. */
    public function comoNomina(): array
    {
        return array_values($this->porCodigo);
    }

    public function total(): int
    {
        return count($this->porCodigo);
    }
}
