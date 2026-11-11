---
type: paridad
title: Mapa de cabecera y semántica Diccionario/Excep/colexcep — Paridad ValidadorRem
description: Documentación de coordenadas/etiquetas de cabecera por plantilla, significado de campos Diccionario (inicio/fin/posicion/tipo_dato) y estructura Excep/colexcep.
tags: [paridad, validacion, cabecera, diccionario, excepciones, rem]
timestamp: 2026-11-10T15:30:00Z
topic: validacion
source: backup_documentacion/MODULO_M7_PROFUNDIZADO.md §7.3, §7.4, §7.5, §17 y esquema BD §4.3
---

# Mapa de cabecera y semántica Diccionario/Excep/colexcep

> **Objetivo F0 (T-F0-04):** Documentar la semántica exacta para replicar la validación V2–V8 con paridad 0 contra ValidadorRem.
> **Riesgo RIES-03, RIES-09:** La semántica de `inicio`/`fin`/`posicion` en `Diccionario` y la estructura `Excep`/`colexcep` **no está confirmada**. No se implementa por intuición: primero se produce el *golden output* del validador actual.

---

## 1. Detección de cabecera (V2)

### 1.1 Estrategia dual (M7 §7.3)

| Método | Cuándo se usa | Descripción |
|---|---|---|
| **Detección por etiqueta** (principal) | Coordenadas no confirmadas / cambian por versión | Recorrer primeras 25 filas × 15 columnas de la hoja 1; normalizar texto (mayúsculas, sin tildes, espacios colapsados); buscar etiquetas y tomar valor contiguo (derecha/abajo, primer no vacío) |
| **Coordenadas fijas** (`plantilla_cabecera`) | Fallback cuando etiqueta no alcanza | Fila/columna exacta (ej. `B3`) definida por admin al crear plantilla |

### 1.2 Etiquetas objetivo (normalizadas)

| Campo | Etiquetas buscadas | Valor esperado | Código error si falla |
|---|---|---|---|
| **Serie** | `SERIE` | Código serie (A, BS, BM, D, P, F, SAX) | ENV-010 |
| **Año** | `ANO`, `AÑO` | Año de 4 dígitos (ej. 2026) | ENV-011 |
| **Mes** | `MES` | Mes 1–12 (concerte con período) | ENV-012 |
| **Versión** | `VERSION`, `VERSIÓN` | Versión plantilla (entero) | ENV-014 |
| **Establecimiento** | `ESTABLECIMIENTO`, `CODIGO`, `CÓDIGO` | Código establecimiento (ej. 123100) | ENV-013 |

### 1.3 Algoritmo de detección (pseudocódigo)

```php
function detectarCabecera(Spreadsheet $hoja): array {
    $etiquetas = [
        'serie' => ['SERIE'],
        'anio' => ['ANO', 'AÑO'],
        'mes' => ['MES'],
        'version' => ['VERSION', 'VERSIÓN'],
        'establecimiento' => ['ESTABLECIMIENTO', 'CODIGO', 'CÓDIGO'],
    ];
    $resultado = [];
    
    for ($fila = 1; $fila <= 25; $fila++) {
        for ($col = 1; $col <= 15; $col++) {
            $celda = normalizar($hoja->getCellByColumnAndRow($col, $fila)->getValue());
            foreach ($etiquetas as $campo => $buscadas) {
                if (in_array($celda, $buscadas) && !isset($resultado[$campo])) {
                    $valor = obtenerValorContiguo($hoja, $fila, $col);
                    if ($valor !== null) {
                        $resultado[$campo] = $valor;
                    }
                }
            }
        }
    }
    return $resultado;
}

function normalizar(string $texto): string {
    return strtoupper(str_replace(['Á','É','Í','Ó','Ú','Ñ',' '], ['A','E','I','O','U','N',''], trim($texto)));
}

function obtenerValorContiguo(Spreadsheet $hoja, int $fila, int $col): ?string {
    // Prioridad: derecha → abajo
    for ($c = $col + 1; $c <= 15; $c++) {
        $v = trim($hoja->getCellByColumnAndRow($c, $fila)->getValue() ?? '');
        if ($v !== '') return $v;
    }
    for ($f = $fila + 1; $f <= 25; $f++) {
        $v = trim($hoja->getCellByColumnAndRow($col, $f)->getValue() ?? '');
        if ($v !== '') return $v;
    }
    return null;
}
```

### 1.4 Tabla `plantilla_cabecera` (fallback)

```sql
CREATE TABLE plantilla_cabecera (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plantilla_id    BIGINT UNSIGNED NOT NULL,
  campo           VARCHAR(30)  NOT NULL,   -- serie | anio | mes | version | establecimiento
  hoja            VARCHAR(100) NULL,       -- hoja donde está (NULL = primera hoja)
  celda           VARCHAR(10)  NULL,       -- ej. "B3"; NULL = detección por etiqueta
  etiqueta        VARCHAR(100) NULL,       -- ej. "SERIE"
  obligatorio     TINYINT(1)   NOT NULL DEFAULT 1,
  KEY ix_pc_plantilla (plantilla_id),
  CONSTRAINT fk_pc_plantilla FOREIGN KEY (plantilla_id) REFERENCES plantillas(id) ON DELETE CASCADE
);
```

> **Uso:** Si `celda` no es NULL → lectura directa; si es NULL → detección por `etiqueta`. Se llena en F0 con archivos reales.

---

## 2. Semántica del Diccionario (V6–V7)

### 2.1 Esquema BD (M7 §4.3)

```sql
CREATE TABLE diccionario (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  snapshot_id        BIGINT UNSIGNED NOT NULL,
  anio               SMALLINT NOT NULL,
  mes                TINYINT  NULL,
  serie              VARCHAR(5) NOT NULL,
  hoja               VARCHAR(100) NOT NULL,
  codigo_prestacion  VARCHAR(20) NOT NULL,
  texto_prestacion   VARCHAR(254) NULL,
  linea              INT NOT NULL,              -- fila en la hoja Excel
  inicio             VARCHAR(10) NULL,          -- columna inicio (ej. "C")
  fin                VARCHAR(10) NULL,          -- columna fin (ej. "H")
  posicion           SMALLINT NULL,             -- índice de columna destino (0-based)
  tipo_dato          VARCHAR(20) NULL,          -- entero | decimal | texto | fecha
  KEY ix_dicc_busqueda (snapshot_id, anio, serie, hoja, linea),
  KEY ix_dicc_prestacion (snapshot_id, codigo_prestacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.2 Interpretación propuesta (PENDIENTE DE CONFIRMACIÓN CON GOLDEN FILES)

> ⚠️ **RIES-03:** La semántica exacta **no está confirmada**. Tres hipótesis:

| Hipótesis | Descripción | Ejemplo |
|---|---|---|
| **A: Rango origen → columna destino** | Leer celdas `[inicio..fin]` en fila `linea`; mapear secuencialmente desde `posicion` | `inicio=C`, `fin=E`, `posicion=0` → C→col0, D→col1, E→col2 |
| **B: Columnas fijas por prestación** | Cada prestación tiene sus columnas definidas; `inicio`/`fin` son fijos por código | Prestación `001` siempre usa C:E; `002` usa G:I |
| **C: Desplazamiento relativo** | `posicion` = offset desde columna base de la hoja; `inicio`/`fin` definen ancho | Base=Col A; `posicion=2` → empieza en C; `inicio..fin` = ancho 3 |

### 2.3 Algoritmo de lectura propuesto (Hipótesis A - base para golden files)

```php
function leerPrestaciones(Spreadsheet $hoja, array $diccionarioFilas): array {
    $registros = [];
    foreach ($diccionarioFilas as $row) {
        $colInicio = columnaALetra($row['inicio'] ?? 'A');
        $colFin    = columnaALetra($row['fin'] ?? $row['inicio']);
        $fila      = $row['linea'];
        $posicion  = $row['posicion'] ?? 0;
        $tipoDato  = $row['tipo_dato'] ?? 'texto';
        
        $valores = [];
        for ($col = $colInicio; $col <= $colFin; $col++) {
            $valor = $hoja->getCellByColumnAndRow($col, $fila)->getValue();
            $valores[] = normalizarTipo($valor, $tipoDato);
        }
        
        $registros[] = [
            'codigo_prestacion' => $row['codigo_prestacion'],
            'linea'             => $fila,
            'valores'           => $valores,
            'posicion_destino'  => $posicion,
        ];
    }
    return $registros;
}

function normalizarTipo($valor, string $tipo): mixed {
    if ($valor === null || $valor === '') return null;
    return match ($tipo) {
        'entero'   => (int) $valor,
        'decimal'  => (float) str_replace(',', '.', $valor),
        'fecha'    => \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($valor),
        default    => trim((string) $valor),
    };
}
```

### 2.4 Validaciones asociadas (V6–V7)

| Etapa | Qué valida | Códigos | Origen en Diccionario |
|---|---|---|---|
| **V6** | Mapeo celdas + lectura | ENV-018 (advertencia: sin datos), ENV-019 (bloqueante: rango ilegible) | `linea`, `inicio`, `fin`, `posicion` |
| **V7** | Tipo dato + decimales | ENV-020 (no numérico), ENV-021 (decimales exceden), ENV-031 (fórmula sin valor) | `tipo_dato`, configuración decimales por prestación |

---

## 3. Excepciones (V8) — `Excep` / `colexcep`

### 3.1 Estructura BD (M7 §4.3 — PENDIENTE P6)

```sql
CREATE TABLE excepciones (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  snapshot_id        BIGINT UNSIGNED NOT NULL,
  anio               SMALLINT NOT NULL,
  mes                TINYINT  NULL,
  serie              VARCHAR(5) NOT NULL,
  hoja               VARCHAR(100) NOT NULL,
  codigo_prestacion  VARCHAR(20) NULL,   -- NULL = aplica a toda la hoja
  columna            VARCHAR(10) NULL,   -- columna exceptuada (ej. "D")
  motivo             VARCHAR(200) NULL,  -- descripción legible
  payload            JSON NULL,          -- respaldo crudo hasta mapear esquema real
  KEY ix_excep_busqueda (snapshot_id, anio, serie, hoja)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 Hipótesis de semántica (PENDIENTE GOLDEN FILES)

| Hipótesis | Descripción |
|---|---|
| **A: Columna no aplica para prestación** | `codigo_prestacion` + `columna` → esa celda debe ir vacía para esa prestación |
| **B: Columna no aplica para hoja completa** | `codigo_prestacion` = NULL → toda la columna `columna` en esa hoja se ignora |
| **C: Matriz (Excep × colexcep)** | `Excep` define prestaciones; `colexcep` define columnas por prestación (tablas separadas en Access) |

> **Estructura Access original:** `Excep` y `colexcep` son tablas relacionadas. En el portal se unifican en `excepciones` con `payload` JSON para preservar datos crudos hasta confirmar mapeo.

### 3.3 Validación V8

| Código | Severidad | Condición |
|---|---|---|
| **ENV-022** | Advertencia | Columna marcada como excepción tiene valor en el archivo |

---

## 4. Hojas y rangos (V5) — `plantilla_hojas` / `Thojas`

### 4.1 Esquema BD

```sql
CREATE TABLE plantilla_hojas (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plantilla_id  BIGINT UNSIGNED NOT NULL,
  hoja          VARCHAR(100) NOT NULL,      -- nombre de la hoja (ej. "CAMAS", "PERSONAL")
  rango         VARCHAR(50)  NULL,          -- rango declarado en Thojas (ej. "A1:Z100")
  obligatoria   TINYINT(1)   NOT NULL DEFAULT 1,
  KEY ix_ph_plantilla (plantilla_id),
  CONSTRAINT fk_ph_plantilla FOREIGN KEY (plantilla_id) REFERENCES plantillas(id) ON DELETE CASCADE
);
```

### 4.2 Validación V5

| Código | Severidad | Condición |
|---|---|---|
| **ENV-016** | Bloqueante | Hoja marcada `obligatoria=1` no existe en el archivo |
| **ENV-017** | Advertencia | Hoja presente en archivo pero no definida en plantilla |

---

## 5. Matriz de decisión para F0 (golden files)

| Concepto | Decisión requerida | Cómo se resuelve |
|---|---|---|
| **Cabecera** | Coordenadas fijas vs detección etiqueta | Archivos reales → medir éxito detección; si < 100% → fijar `plantilla_cabecera` |
| **Diccionario: inicio/fin/posicion** | Hipótesis A/B/C | Golden output ValidadorRem → ajustar lector hasta coincidir 100% |
| **Diccionario: tipo_dato** | Valores permitidos | Catálogo Access → mapear a `entero|decimal|texto|fecha` |
| **Excepciones** | Hipótesis A/B/C | Golden output → verificar qué celdas se ignoran |
| **Hojas obligatorias** | Nombres exactos por serie | Thojas exportado → `plantilla_hojas` |

---

## 6. Procedimiento F0 (T-F0-04)

1. **Exportar catálogos Access** → CSV UTF-8: `mpServicio`, `mpEstablecimiento`, `version`, `Thojas`, `Diccionario`, `Excep`, `colexcep` (M7 §17.1)
2. **Ejecutar ValidadorRem** con 12-20 archivos reales anonimizados (T-F0-02/03)
3. **Comparar salidas** (`datos` + `LogError`) con lector propuesto
4. **Documentar hallazgos** en este archivo: semántica confirmada, coordenadas fijas, reglas excepciones
5. **Actualizar algoritmos** V2, V5, V6, V7, V8 según evidencia

---

## 7. Estado actual

| Componente | Estado | Acción requerida |
|---|---|---|
| Detección cabecera (V2) | Algoritmo propuesto | Probar con golden files; completar `plantilla_cabecera` |
| Diccionario (V6–V7) | Hipótesis A documentada | Confirmar con golden output; ajustar lector |
| Excepciones (V8) | Estructura BD + 3 hipótesis | Confirmar con golden output; mapear Excep/colexcep |
| Hojas (V5) | Esquema BD + algoritmo | Validar con Thojas real |

---

**Archivo guardado en:** `C:\xampp\htdocs\www\CargaREM\docs\validacion\paridad-cabecera-diccionario.md`

> **Nota:** Este documento es **vivo**. Se actualiza en F0 con cada hallazgo de los golden files. La implementación final de V2–V8 **no comienza** hasta que la semántica quede confirmada por paridad (Principio 10).