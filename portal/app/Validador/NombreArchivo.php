<?php

namespace App\Validador;

/**
 * Metadatos que van codificados en el nombre del archivo REM (RF-040).
 *
 * Formato oficial DEIS: `[código 6][serie 1-3][mes 2].(xlsx|xlsm)`
 *
 *     123100A02.xlsm    →  Hospital Base San José, serie A, febrero
 *     123010P06.xlsm    →  Dirección SS, serie P, corte de junio
 *     123100SAX02.xlsm  →  Hospital Base San José, serie Anexo, febrero
 *
 * Se acepta además una variante con separadores (`REM_A01_123100_2026.xlsx`),
 * porque algunos equipos renombran los archivos antes de enviarlos.
 */
final class NombreArchivo
{
    private const FORMATO_DEIS = '/^(\d{6})([A-Za-z]{1,3})(\d{2})\.(xlsx|xlsm)$/i';

    /** Variante tolerada: REM_A01_123100_2026.xlsx o A01-123100-2026.xlsm */
    private const FORMATO_SEPARADO = '/^(?:REM[_-])?([A-Za-z]{1,3})(\d{2})[_-](\d{6})(?:[_-](\d{4}))?\.(xlsx|xlsm)$/i';

    /** Series que el catálogo de la Unidad reconoce. */
    public const SERIES_RECONOCIDAS = ['A', 'P', 'D', 'BM', 'BS', 'SAX'];

    /**
     * La serie P se informa dos veces al año: corte de junio y de diciembre.
     * Es la misma regla que el portal exige en `MatrizSeries` (R-P-01).
     *
     * @var array<string, list<string>>
     */
    public const MESES_POR_SERIE = [
        'A'   => ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'],
        'P'   => ['06', '12'],
        'D'   => ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'],
        'BM'  => ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'],
        'BS'  => ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'],
        'SAX' => ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'],
    ];

    /**
     * Hojas obligatorias por serie. Vacío = solo se revisa la portada.
     *
     * @var array<string, list<string>>
     */
    public const HOJAS_OBLIGATORIAS = [
        'P' => ['NOMBRE', 'P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'P7', 'P9', 'P11', 'P12', 'P13'],
    ];

    /**
     * Versiones de plantilla aceptadas en la celda A9 de la hoja NOMBRE.
     * Una serie sin entrada acepta cualquier valor: la versión no se revisa.
     * **No hay valor por defecto**: comparar una serie contra las versiones de
     * otra marcaría como error todo archivo correcto de esa serie.
     *
     * @var array<string, list<string>>
     */
    public const VERSIONES_ACEPTADAS = [
        'A' => ['Versión 1.2: Febrero 2026', 'Versión 1.1: Febrero 2026'],
        'P' => ['Versión 1.2: Junio 2026'],
    ];

    /**
     * @param  list<string>  $errores
     */
    private function __construct(
        public readonly string $nombreOriginal,
        public readonly string $codigo,
        public readonly string $serie,
        public readonly string $mes,
        public readonly string $extension,
        public readonly array $errores = [],
    ) {
    }

    /** @param  list<string>  $errores */
    public static function invalido(string $nombre, array $errores, string $serie = '', string $mes = ''): self
    {
        return new self($nombre, '', $serie, $mes, '', $errores);
    }

    public function esValido(): bool
    {
        return $this->errores === [];
    }

    /** @param  list<string>  $hojasPresentes */
    public function hojasObligatoriasFaltantes(array $hojasPresentes): array
    {
        $requeridas = self::HOJAS_OBLIGATORIAS[strtoupper($this->serie)] ?? [];

        $presentes = array_map(fn ($h) => mb_strtoupper(trim((string) $h)), $hojasPresentes);

        return array_values(array_filter(
            $requeridas,
            fn (string $hoja) => ! in_array(mb_strtoupper($hoja), $presentes, true),
        ));
    }

    /** @return list<string> */
    public static function versionesAceptadas(string $serie): array
    {
        return self::VERSIONES_ACEPTADAS[strtoupper($serie)] ?? [];
    }

    public static function mesEsValidoParaSerie(string $serie, string $mes): bool
    {
        return in_array($mes, self::MESES_POR_SERIE[strtoupper($serie)] ?? [], true);
    }

    /** Texto para el mensaje: «01 a 12» o «06 o 12». */
    public static function etiquetaMeses(string $serie): string
    {
        $meses = self::MESES_POR_SERIE[strtoupper($serie)] ?? [];

        if ($meses === []) {
            return 'una serie habilitada';
        }

        if (count($meses) === 12) {
            return '01 a 12';
        }

        return implode(' o ', $meses);
    }

    public static function parsear(string $nombre): self
    {
        if (! preg_match('/\.(xlsx|xlsm)$/i', $nombre)) {
            return self::invalido($nombre, ['El archivo debe tener extensión .xlsx o .xlsm.']);
        }

        $partes = self::extraerPartes($nombre);

        if ($partes === null) {
            return self::invalido($nombre, [
                'El nombre del archivo no sigue el formato DEIS. Se espera [código de 6 dígitos][serie][mes].xlsx, '
                . 'por ejemplo 123100A02.xlsx o 123100SAX02.xlsx.',
            ]);
        }

        $errores = [];
        $serie = strtoupper($partes['serie']);
        $mes = $partes['mes'];

        if (! in_array($serie, self::SERIES_RECONOCIDAS, true)) {
            $errores[] = 'Serie no reconocida: «' . $serie . '». Series válidas: '
                . implode(', ', self::SERIES_RECONOCIDAS) . '.';
        }

        $mesNumero = (int) $mes;

        if ($mesNumero < 1 || $mesNumero > 12) {
            $errores[] = 'Mes inválido: «' . $mes . '». Debe estar entre 01 y 12.';
        } elseif (in_array($serie, self::SERIES_RECONOCIDAS, true) && ! self::mesEsValidoParaSerie($serie, $mes)) {
            $errores[] = 'El mes «' . $mes . '» no corresponde a la serie ' . $serie
                . '. Debe ser ' . self::etiquetaMeses($serie) . '.';
        }

        return new self(
            nombreOriginal: $nombre,
            codigo: $partes['codigo'],
            serie: $serie,
            mes: str_pad($mes, 2, '0', STR_PAD_LEFT),
            extension: strtolower($partes['extension']),
            errores: $errores,
        );
    }

    /**
     * @return array{codigo: string, serie: string, mes: string, extension: string}|null
     */
    private static function extraerPartes(string $nombre): ?array
    {
        if (preg_match(self::FORMATO_DEIS, $nombre, $m)) {
            return ['codigo' => $m[1], 'serie' => $m[2], 'mes' => $m[3], 'extension' => $m[4]];
        }

        if (preg_match(self::FORMATO_SEPARADO, $nombre, $m)) {
            return ['codigo' => $m[3], 'serie' => $m[1], 'mes' => $m[2], 'extension' => $m[5]];
        }

        return null;
    }
}
