# Módulo M2 Profundizado: Carga de Datos

Versión documentada: 1.0 | Estado: ✅ Implementado | Fecha: 2026-11-05

---

## 1. Arquitectura del Módulo

### Componentes Principales

| Componente | Tipo | Propósito | Implementación |
|------------|------|-----------|----------------|
| **CargaDatos** | Formulario | Wizard de carga individual (1 archivo) | ✅ |
| **CargaDatosMultiple** | Formulario | Procesador por carpeta (múltiples archivos) | ✅ |
| **ActDBServ** | Formulario | Consumidor de WebService (datos servidor) | ✅ |
| **CargaExcel** | Clase mecánica | Motor de validación | ✅ |
| **bases** | Clase estática | Operaciones CRUD | ✅ |

---

## 2. Flujos Detallados

### F2.1: Flujo de Carga Individual (CargaDatos)

```mermaid
sequenceDiagram
    participant User
    participant UI as CargaDatos
    participant Engine as CargaExcel
    participant BD as Base de datos
    
    User->>UI: 1. Selecciona Serie, Año, Mes
    UI->>UI: Valida SS seleccionado (RN-G1)
    User->>UI: 2. Selecciona archivo Excel
    UI->>Engine: 3. ProcesaArchivo()
    Engine->>Engine: verifica_version()
    Engine->>Engine: comprueba_estab()
    Engine->>Engine: Busca_Hoja()
    Engine->>Engine: Busca_Dato()
    Engine->>Engine: VeriExcep()
    Engine->>BD: Persistencia datos/errores
    BD->>UI: Confirmación
    UI->>User: 4. Mensaje resultado
```

### F2.2: Flujo de Carga Múltiple (CargaDatosMultiple)

```mermaid
flowchart LR
    A[Seleccionar carpeta] --> B[Listar archivos Excel]
    B --> C{¿Archivos encontrados?}
    C -- Sí --> D[Para cada archivo: ProcesaArchivo]
    D --> E[ProgressBar: progreso]
    E --> F[Acumular resultados]
    F --> G{¿Todos procesados?}
    G -- No --> D
    G -- Sí --> H[Mostrar resumen]
    C -- No --> I[Mensaje: "Debe Ingresar Ruta"]
```

### F2.3: Flujo de Descarga desde Servidor (ActDBServ)

```mermaid
sequenceDiagram
    participant User
    participant UI as ActDBServ
    participant WS as WebService MINSAL
    participant BD as Base de datos local
    
    User->>UI: Click "Datos desde Servidor"
    UI->>User: Mensaje confirmación<br/>"Se borrarán todos los datos..."
    User->>UI: Confirmar
    UI->>WS: Llamada DatosSerie(códigoSS, año, mes)
    WS->>UI: DataSet con datos
    UI->>BD: crea_base() + Insertar datos
    BD->>UI: Confirmación
    UI->>User: Mensaje éxito/error
```

---

## 3. Reglas de Negocio Detalladas

### RN-M2-1: Extensiones Admitidas

| Extensión | Tecnología | Conexión |
|-----------|------------|----------|
| `.xls` | Microsoft.ACE.OLEDB.12.0 | `Excel 8.0;HDR=Yes;IMEX=1` |
| `.xlsx` | NPOI 2.3 | Método `Carga_NombreNPOI` |
| `.xlsm` | NPOI 2.3 | Igual que .xlsx |

**Validaciones:**
- Filtro: `Archivos Excel|*.xls;*.xlsx;*.xlsm`
- Rechaza automáticamente otros formatos

### RN-M2-2: Validación de Cabecera

| Validación | Mensaje de Error | Ubicación |
|------------|------------------|-----------|
| Serie | "Serie no Corresponde: Por favor verifique la serie del Archivo" | Hoja A1 |
| Año | "Año no Corresponde: Por favor verifique año del Archivo" | Cell específica |
| Mes | "Mes no coincide: Por Favor Verifique que el texto y el número del mes coincidan" | Texto y número |
| Establecimiento | "Establecimiento no Corresponde con Servicio: Por favor verifique código de Establecimiento del Archivo" | Comparación SS |
| Versión | "Versión No Corresponde a Mes: por Favor Verifique la Versión con el Mes de Ingreso de Archivo" | Tabla `version` |

### RN-M2-3: Versión de Plantilla

**Tabla `version` (Global.mdb):**

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `ano` | Long | Año del registro |
| `mes` | Long | Mes (1-12) |
| `Serie` | WChar | Serie (A, BS, BM, D, P, F) |
| `SerieExcel` | WChar | Serie según plantilla |
| `version` | WChar | Versión específica |

**Volumen:** 939 filas

### RN-M2-5: Confirmación de Sobrescritura

**Mensaje:**
```
"ATENCION: Se sobreescribirá la información relacionada a los archivos 
a cargar si es que ya se encuentran en la base de datos. 
¿Desea continuar?"
```

**Operaciones de borrado:**
```sql
DELETE FROM datos WHERE ano = @ano AND mes = @mes 
                      AND idestablecimiento = @establecimiento 
                      AND serie = @serie;

DELETE FROM LogError WHERE ano = @ano AND mes = @mes 
                         AND idestablecimiento = @establecimiento 
                         AND serie = @serie;

DELETE FROM HistCarga WHERE ano = @ano AND mes = @mes 
                          AND IdEstablecimiento = @establecimiento 
                          AND Serie = @serie;
```

---

## 4. Estructuras de Datos

### Tabla `datos`

```sql
CREATE TABLE datos (
    ano LONG,
    mes LONG,
    idservicio LONG,
    idestablecimiento TEXT(50),
    codigoprestacion TEXT(20),
    col01 DOUBLE,
    col02 DOUBLE,
    -- ... col03 a col50
    col50 DOUBLE,
    fechacarga DATETIME
);
```

### Tabla `LogError` (Trans.mdb)

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `archivo` | WChar | Nombre del archivo con error |
| `idestablecimiento` | WChar | Código del establecimiento |
| `mes` | Long | Mes del registro |
| `ano` | Long | Año del registro |
| `serie` | WChar | Serie del archivo |
| `linea` | Long | Línea en Excel |
| `hoja` | WChar | Nombre de hoja |
| `dato` | WChar | Valor del campo con error |
| `mensaje_error` | MEMO | Descripción del error |
| `fecharegistro` | DATETIME | Cuando se registró |

### Tabla `INCORRECTO` (TemporalIncorrectos.mdb)

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `ano` | LONG | Año |
| `mes` | LONG | Mes |
| `idservicio` | LONG | Servicio |
| `idestablecimiento` | TEXT(50) | Establecimiento |
| `codigoprestacion` | TEXT(20) | Prestación |
| `col01` - `col50` | DOUBLE | Valores |
| `fechacarga` | DATETIME | Timestamp |
| `motivo_error` | MEMO | Descripción error |

---

## 5. Componentes del Motor CargaExcel

| Componente | Función | Referencias |
|------------|---------|-------------|
| `verifica_version()` | Valida versión plantilla | Tabla `version` |
| `comprueba_estab()` | Valida código establecimiento | Tabla `mpEstablecimiento` |
| `Busca_Hoja()` | Busca hoja en Excel | Tabla `Thojas` |
| `Busca_Dato()` | Extrae valor de celda | Diccionario + tipoDato |
| `VeriExcep()` | Valida excepciones | Tablas `Excep`, `colexcep` |

---

## 6. Tabla de Referencia de Catálogos

| Tabla | Ubicación | Filas | Uso |
|-------|-----------|-------|-----|
| `Diccionario` | Global.mdb | 185,526 | Mapeo celdas → prestaciones |
| `mpEstablecimiento` | Global.mdb | 3,207 | Validación SS |
| `version` | Global.mdb | 939 | Versiones plantilla |
| `Thojas` | Global.mdb | 1,129 | Estructura hojas |
| `Excep` | Global.mdb | 5,664 | Columnas no aplicables |
| `colexcep` | Global.mdb | 2,977 | Detalle excepciones |
| `mpServicio` | Global.mdb | 30 | Lista servicios |

---

## 7. Estructura por Serie

| Serie | Hojas Excel | Uso |
|-------|-------------|-----|
| **A** | "Prestaciones", "Resumen" | Hospitales generales |
| **BS** | "Prestaciones", "Resumen" | Servicios especializados |
| **BM** | "Prestaciones", "Resumen" | Balnearios/Salud ambiente |
| **D** | "Prestaciones", "Resumen" | Discapacitados |
| **P** | "Prestaciones", "Resumen" | Particulares |
| **F** | "Prestaciones", "Resumen" | Fuerzas Armadas |

---

## 8. Mensajes de UI

| Estado | Mensaje |
|--------|---------|
| Sin SS | "Por favor debe Elegir Servicio de Salud..." |
| Sin selección | "Debe Seleccionar Opciones" |
| Extensión inválida | (rechaza archivo) |
| Error cabecera | Mensaje según RN-M2-2 |
| Sobrescritura | "ATENCION: Se sobreescribirá..." |
| Éxito | "Proceso Finalizado con Éxito" / "Proceso Finalizado con Exito !!" |
| Error | "Ejecutado con error" |
| Cancelado | "Operacion Cancelada" |
| Sin datos | "No hay Datos a Ingresar" |

---

## 9. Web Services Involucrados

| Web Service | Operación | Parámetros | Uso |
|-------------|-----------|------------|-----|
| `CargaSerieServ` | `DatosSerie` | (string códigoSS, int año, int mes) | Descargar datos por SS |
| `CargaControlServ` | `DatosControl` | (string códigoSS, int año, int mes) | Descargar datos control |
| `CargaSerie` | `serieA, serieBS...` | (string códigoSS) | Descargar por serie |

---

## 10. Volúmenes Típicos

| Tabla | Filas año típico | Ubicación |
|-------|------------------|-----------|
| `datos` | 15,000+ | DatosSERIE{serie}{año}.mdb |
| `HistCarga` | ~4,000 | Trans.mdb |
| `cargados` | 602 | Temporal.mdb |

---

## 11. Consideraciones Técnicas

### Tecnologías
- **Excel Reading:** NPOI 2.3, Microsoft.ACE.OLEDB.12.0
- **Persistencia:** OleDb (Jet/ACE 32-bit)
- **Exportes:** Microsoft.Office.Interop.Excel 14.0

### Puntos críticos
1. `KillAllAddedExcelProcesses()` para limpieza procesos Excel
2. Manejo especial códigos con guion (RN-M2-10)
3. Validación estricta de versiones plantilla

---

## 12. Errores Comunes

| Tipo de Error | Ubicación | Acción |
|---------------|-----------|--------|
| Extensión inválida | Antes carga | Rechazar archivo |
| Cabecera incompatible | verifica_version | Registrar LogError |
| Estructura hoja | Busca_Hoja | Continuar hoja siguiente |
| Tipo de dato inválido | Busca_Dato | Registrar error, continuar |

---

## 13. Estado de Validación Pendiente

| Elemento | Estado | Comentario |
|----------|--------|------------|
| Límite tamaño archivo | ❌ Pendiente | No especificado |
| Macro .xlsm | ❌ Pendiente | No se ejecutan |
| Concurrencia .mdb | ❌ Pendiente | Manejo archivos bloqueados |

---

## 14. Métricas

- **CargaDatos:** Rutina principal ~6,717 bytes IL
- **Métodos CargaExcel:** verifica_version, comprueba_estab, Busca_Hoja, Busca_Dato, VeriExcep
- **Consultas SQL:** Multiple SELECT, INSERT, DELETE
- **Componentes:** 5 (3 formularios, 2 clases)

---

**Documento generado:** Basado en análisis estático del binario. Requiere validación con ejecución en entorno de prueba.