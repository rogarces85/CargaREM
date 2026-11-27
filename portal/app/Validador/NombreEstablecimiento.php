<?php

namespace App\Validador;

/**
 * Contraste del nombre del establecimiento de la portada contra la nómina.
 *
 * Ese nombre lo teclea una persona, así que la comparación no puede ser
 * carácter por carácter: llega en mayúsculas, sin tildes, con el tipo de
 * recinto abreviado o con una errata. Lo que sí tiene que detectar es el caso
 * que motiva la regla: **que el nombre sea el de otro establecimiento**.
 *
 * Un nombre de la nómina son dos cosas pegadas, y no se comparan igual:
 *
 *     Posta de Salud Rural  |  Coligual
 *     el tipo de recinto       quién es
 *     (lo repiten 31)          (lo distingue)
 *
 * El tipo se compara por identidad —se reduce a una clave— y la parte
 * distintiva por letras. Medir las erratas sobre el nombre completo era el
 * error: «posta salud rural Coligual» está a tres caracteres del nombre
 * oficial, y esos tres caracteres caen justo en la mitad que no identifica a
 * nadie.
 */
final class NombreEstablecimiento
{
    /** La nómina escribe el tipo en extenso y el REM lo abrevia. */
    private const ABREVIATURAS = [
        ['/\bp\s?s\s?r\b/', 'posta de salud rural'],
        ['/\bposta rural\b/', 'posta de salud rural'],
        ['/\bcecosf\b/', 'centro comunitario de salud familiar'],
        ['/\bcesfam\b/', 'centro de salud familiar'],
        ['/\bcsf\b/', 'centro de salud familiar'],
        ['/\bhosp\b/', 'hospital'],
        ['/\bdoctor\b/', 'dr'],
        ['/\bdoctora\b/', 'dra'],
    ];

    /** Palabras que no distinguen a nadie y aparecen o no según quién escriba. */
    private const VACIAS = ['de', 'del', 'la', 'las', 'el', 'los', 'y', 'en', 'con'];

    /**
     * Tipos de recinto, de más específico a menos. Gana el primero cuya palabra
     * delatora esté presente: «comunitario» antes que «familiar» porque un
     * CECOSF trae las dos y un CESFAM solo la segunda, y son tipos distintos.
     *
     * @var array<string, array{delata: string, palabras: list<string>}>
     */
    private const TIPOS = [
        'CECOSF'    => ['delata' => 'comunitario', 'palabras' => ['centro', 'comunitario', 'salud', 'familiar']],
        'CESFAM'    => ['delata' => 'familiar', 'palabras' => ['centro', 'salud', 'familiar']],
        'POSTA'     => ['delata' => 'posta', 'palabras' => ['posta', 'salud', 'rural']],
        'HOSPITAL'  => ['delata' => 'hospital', 'palabras' => ['hospital', 'base']],
        'SAPU'      => ['delata' => 'sapu', 'palabras' => ['sapu']],
        'CECOSF_2'  => ['delata' => 'consultorio', 'palabras' => ['consultorio']],
    ];

    /** Normaliza: sin tildes, sin mayúsculas, sin puntuación. */
    public static function normalizar(string $texto): string
    {
        // Se reemplazan las letras acentuadas una por una en vez de confiar en
        // la descomposición Unicode: PHP entrega «José» ya compuesto y el
        // `preg_replace` de marcas combinantes no lo tocaría.
        $sinTildes = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
        ]);

        $sinTildes = mb_strtolower($sinTildes, 'UTF-8');

        // Marcas combinantes sueltas, por si el origen venía descompuesto.
        $sinTildes = preg_replace('/[\x{0300}-\x{036F}]/u', '', $sinTildes) ?? $sinTildes;
        $sinTildes = preg_replace('/[.,;:()\[\]{}\-_\'"`´¡!¿?\/\\\\]/u', ' ', $sinTildes) ?? $sinTildes;

        return trim(preg_replace('/\s+/', ' ', $sinTildes) ?? $sinTildes);
    }

    /**
     * ¿El nombre escrito corresponde al establecimiento esperado?
     *
     * @param  list<array{codigo: string, nombre: string, alias?: list<string>}>  $nomina
     * @return array{coincide: bool, esperado: string, nombrado: ?array}
     */
    public static function verificar(string $escrito, array $esperado, array $nomina): array
    {
        $normalizado = self::normalizar($escrito);

        if ($normalizado === '') {
            return ['coincide' => true, 'esperado' => (string) $esperado['nombre'], 'nombrado' => null];
        }

        // Un alias explícito («HBSJO», «SAMU») es una coincidencia declarada.
        foreach ($esperado['alias'] ?? [] as $alias) {
            if ($normalizado === self::normalizar((string) $alias)) {
                return ['coincide' => true, 'esperado' => (string) $esperado['nombre'], 'nombrado' => null];
            }
        }

        if (self::seParecen($normalizado, (string) $esperado['nombre'])) {
            return ['coincide' => true, 'esperado' => (string) $esperado['nombre'], 'nombrado' => null];
        }

        // No se parece al suyo. ¿Se parece al de otro? Ese es el hallazgo que
        // importa: el archivo quedaría cargado a nombre del vecino.
        $nombrado = null;
        $mejor = 0.0;

        foreach ($nomina as $otro) {
            if ((string) $otro['codigo'] === (string) $esperado['codigo']) {
                continue;
            }

            $puntaje = self::seParecenDetallado($normalizado, (string) $otro['nombre']);

            if ($puntaje > $mejor) {
                $mejor = $puntaje;
                $nombrado = $otro;
            }
        }

        return [
            'coincide' => false,
            'esperado' => (string) $esperado['nombre'],
            'nombrado' => $mejor >= 0.62 ? $nombrado : null,
        ];
    }

    private static function seParecen(string $escrito, string $oficial): bool
    {
        return self::seParecenDetallado($escrito, $oficial) >= 0.62;
    }

    /**
     * Puntaje de 0 a 1 entre dos nombres.
     *
     * Primero se comparan las **partes distintivas** (sin el tipo de recinto y
     * sin palabras vacías); si coinciden, es el mismo establecimiento y da
     * igual cómo se escribió el encabezado. Si no, se compara el nombre
     * completo con tolerancia de edición.
     */
    private static function seParecenDetallado(string $escrito, string $oficial): float
    {
        $a = self::partes($escrito);
        $b = self::partes($oficial);

        if ($a['distintivo'] !== '' && $a['distintivo'] === $b['distintivo']) {
            return 1.0;
        }

        $distancia = self::distancia($a['distintivo'] !== '' ? $a['distintivo'] : $a['completo'], $b['distintivo'] !== '' ? $b['distintivo'] : $b['completo']);
        $largo = max(1, max(strlen($a['distintivo'] ?: $a['completo']), strlen($b['distintivo'] ?: $b['completo'])));
        $porDistancia = 1.0 - ($distancia / $largo);

        // Si el tipo de recinto no coincide, baja el puntaje: «Hospital X» y
        // «CESFAM X» son establecimientos distintos aunque compartan el nombre.
        $mismoTipo = $a['tipo'] === null || $b['tipo'] === null || $a['tipo'] === $b['tipo'];

        return $mismoTipo ? $porDistancia : $porDistancia * 0.5;
    }

    /**
     * @return array{tipo: ?string, distintivo: string, completo: string}
     */
    private static function partes(string $texto): array
    {
        // Se normaliza aquí y no en quien llama: el nombre oficial viene de la
        // nómina con mayúsculas y tildes, y el escrito también. Si la
        // normalización quedaba solo en un lado, «San José» y «san josé» no
        // coincidían y todo archivo correcto se marcaba como nombre ajeno.
        $normalizado = self::normalizar($texto);
        $expandido = $normalizado;

        foreach (self::ABREVIATURAS as [$patron, $reemplazo]) {
            $expandido = preg_replace($patron, $reemplazo, $expandido) ?? $expandido;
        }

        $palabras = array_values(array_filter(
            explode(' ', $expandido),
            fn (string $p) => $p !== '' && ! in_array($p, self::VACIAS, true),
        ));

        $tipo = null;

        foreach (self::TIPOS as $clave => $definicion) {
            if (in_array($definicion['delata'], $palabras, true)) {
                $tipo = str_replace('_2', '', $clave);
                break;
            }
        }

        $vocabularioTipo = [];

        foreach (self::TIPOS as $definicion) {
            $vocabularioTipo = array_merge($vocabularioTipo, $definicion['palabras']);
        }

        $distintivo = implode(' ', array_values(array_filter(
            $palabras,
            fn (string $p) => ! in_array($p, $vocabularioTipo, true),
        )));

        return [
            'tipo'       => $tipo,
            'distintivo' => $distintivo,
            'completo'   => implode(' ', $palabras),
        ];
    }

    /** Distancia de Levenshtein. */
    private static function distancia(string $a, string $b): int
    {
        if ($a === $b) {
            return 0;
        }

        if ($a === '') {
            return strlen($b);
        }

        if ($b === '') {
            return strlen($a);
        }

        $anterior = range(0, strlen($b));
        $actual = array_fill(0, strlen($b) + 1, 0);

        for ($i = 1; $i <= strlen($a); $i++) {
            $actual[0] = $i;

            for ($j = 1; $j <= strlen($b); $j++) {
                $costo = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $actual[$j] = min(
                    $actual[$j - 1] + 1,
                    $anterior[$j] + 1,
                    $anterior[$j - 1] + $costo,
                );
            }

            [$anterior, $actual] = [$actual, $anterior];
        }

        return $anterior[strlen($b)];
    }
}
