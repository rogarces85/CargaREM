# Flujo de Datos Integral - Sistema ValidadorRem

Versión: 1.0 | Fecha: 2026-11-05 | Estado: ✅ Completo

---

## 📑 ÍNDICE

1. [Arquitectura General](#1-arquitectura-general)
2. [Módulo M1 - Arranque y Configuración](#2-módulo-m1-arranque-y-configuración)
3. [Módulo M2 - Carga de Datos](#3-módulo-m2-carga-de-datos)
4. [Módulo M3 - Errores y Control](#4-módulo-m3-errores-y-control)
5. [Módulo M4 - Consulta e Informes](#5-módulo-m4-consulta-e-informes)
6. [Módulo M5 - Mantenimiento y Exportación](#6-módulo-m5-mantenimiento-y-exportación)
7. [Módulo M6 - Sincronización](#7-módulo-m6-sincronización)
8. [Flujos Integrados](#8-flujos-integrados)
9. [Diagramas ER Principales](#9-diagramas-er-principales)
10. [API Endpoints Propuestos](#10-api-endpoints-propuestos)

---

## 1. Arquitectura General

### Tecnología Base
- **Plataforma:** Windows Forms (.NET Framework 4.8, x86)
- **Base de datos:** Microsoft Access (Jet/ACE 32-bit)
- **Excel Reading:** NPOI 2.3 + Microsoft.ACE.OLEDB.12.0
- **Web Services:** SOAP ASMX
- **Exportación:** Microsoft.Office.Interop.Excel 14.0

### Estructura de Carpetas
```
Instalación/
├── Global.mdb          (422 MB) - Catálogos maestros
├── Trans.mdb           - Histórico, SetServicio
├── Datos/              - Por serie y año
│   └── DatosSERIE{serie}{año}.mdb
├── Control/            - Control por serie
│   └── ControlSERIE{serie}{año}.mdb
├── Temporal/           - Tablas temporales
├── MASTER.mdb          - Plantilla MINSAL
└── ValidadorRem.exe    - Aplicación principal
```

---

## 2. Módulo M1 - Arranque y Configuración

### Componentes
- **Principal:** Shell principal con menú (13 botones)
- **Seteo:** Selección de Servicio de Salud
- **buscaActualizacion:** Splash actualizaciones

### Flujo de Arranque

```mermaid
flowchart TD
    A[Inicio STAThread] --> B[Splash: Cargando...]
    B --> C[Verificar Actualizado.txt]
    C --> D{Archivo existe?}
    D -- Sí --> E[Actualizacion.actualizar]
    D -- No --> F[Verificar conectividad WS]
    F --> G[WebService1.version()]
    F --> H[VersionEjecutable.VersionEjec()]
    G --> I[Comparar versiones]
    H --> I
    I --> J{¿Diferente?}
    J -- Sí --> K[Mensaje: Actualizaciones]
    J -- No --> L[Mostrar Principal]
    K --> L
    L --> M{¿SS seleccionado?}
    M -- Sí --> N[Operaciones habilitadas]
    M -- No --> O[Operaciones bloqueadas RN-G1]
```

### Flujo Selección SS

```mermaid
flowchart TB
    A[Seteo: Menú → Seleccionar S.S.] --> B[SELECT COUNT FROM SetServicio]
    B --> C{Ya existe?}
    C -- Sí --> D[Mensaje: "Ya seleccionó SS"]
    C -- No --> E[SELECT FROM mpservicio]
    E --> F[Usuario elige]
    F --> G[INSERT INTO SetServicio]
    G --> H["Éxito: 'Datos ingresados...'"]
```

---

## 3. Módulo M2 - Carga de Datos

### Componentes
- **CargaDatos:** Carga individual
- **CargaDatosMultiple:** Carga por carpeta
- **ActDBServ:** Descarga servidor
- **CargaExcel:** Motor de validación

### Flujo Carga Individual

```mermaid
sequenceDiagram
    participant User
    participant UI as CargaDatos
    participant Engine as CargaExcel
    participant BD as Bases datos
    
    User->>UI: Selecciona Serie/Año/Mes
    User->>UI: Selecciona archivo Excel
    UI->>Engine: ProcesaArchivo()
    Engine->>Engine: verifica_version
    Engine->>Engine: comprueba_estab
    Engine->>Engine: Busca_Hoja
    Engine->>Engine: Busca_Dato
    Engine->>BD: INSERT datos / LogError
    BD->>UI: Confirmación
    UI->>User: Mensaje resultado
```

### Flujo Integrado

```mermaid
flowchart TB
    subgraph "Entrada"
        A[Usuario: Archivo Excel]
    end
    
    subgraph "Validaciones RN-M2"
        B[RN-M2-1: Extensión válida]
        C[RN-M2-2: Cabecera correcta]
        D[RN-M2-3: Versión plantilla]
    end
    
    subgraph "Motor CargaExcel"
        E[verifica_version → tabla version]
        F[comprueba_estab → mpEstablecimiento]
        G[Busca_Hoja → Thojas + Diccionario]
        H[Busca_Dato → tipoDato]
    end
    
    subgraph "Salidas"
        I[datos tabla: registros válidos]
        J[LogError tabla: errores]
        K[HistCarga tabla: histórico]
        L[Mensaje: éxito/error]
    end
    
    A --> B & C & D
    B --> E & F & G & H
    E & F & G & H --> I & J & K
    I & J & K --> L
```

---

## 4. Módulo M3 - Errores y Control

### Flujo de Registro de Errores

```mermaid
flowchart TB
    A[CargaDatos/CargaMultiple] --> B[CargaExcel valida]
    B --> C{¿Error?}
    C -- Sí --> D[INSERT LogError]
    C -- Sí --> E[INSERT INCORRECTO]
    C -- No --> F[INSERT datos]
    D --> G[LogErrorCargados UI]
    E --> H[InformeControl]
    G --> I[Exportar Excel]
    H --> J[Calcular puntos]
```

### Estructura LogError

```sql
CREATE TABLE LogError (
    archivo TEXT(255),
    idestablecimiento TEXT(50),
    mes LONG, ano LONG, serie TEXT(10),
    linea LONG, hoja TEXT(100),
    dato TEXT(255), mensaje_error MEMO,
    fecharegistro DATETIME
);
```

---

## 5. Módulo M4 - Consulta e Informes

### Flujo Matriz Establecimientos

```mermaid
flowchart LR
    A[Usuario: cbAno, cbSerie] --> B[CREATE TABLE cargados]
    B --> C[INSERT SELECT DISTINCT datos]
    C --> D[SELECT idestablecimiento, Ene,Dic columns]
    D --> E[dgvMatriz: visualización]
    E --> F[btnExportar]
```

### Flujo Detección Duplicados

```mermaid
flowchart LR
    A[Usuario: cbAno, cbSerie] --> B[GROUP BY + HAVING COUNT > 1]
    B --> C[dgvDuplicados]
    C --> D[btnExportar]
```

---

## 6. Módulo M5 - Mantenimiento y Exportación

### Flujo Borrado Datos

```mermaid
flowchart TB
    A[Eliminar: Año/Mes/Serie/Estado] --> B[Validación selección]
    B --> C[Confirmación]
    C --> D[Borrado en cascada]
    D --> E[BASE: Elimina_Historico]
    D --> F[BASE: borra_datos]
    D --> G[DELETE LogError]
    E & F & G --> H["Éxito: 'Datos eliminados...'"]
```

### Flujo Generación MINSAL

```mermaid
flowchart TB
    A[Minsal: Año/Mes] --> B[Preparar: copia MASTER.mdb]
    B --> C[Insert datos por serie]
    C --> D[Insert registros estadísticas]
    D --> E[Compresión GZip]
    E --> F[Limpieza temporal]
    F --> G["Éxito: 'Archivo generado...'"]
```

---

## 7. Módulo M6 - Sincronización

### Flujo Actualización Catálogos

```mermaid
flowchart LR
    A[Actualizador] --> B[WebService1.version]
    A --> C[VersionEjecutable.VersionEjec]
    B --> D[Comparar versiones]
    C --> D
    D --> E{¿Actualizar?}
    E -- Sí --> F[Eliminar Global.mdb tabs]
    F --> G[Descargar catálogos:<br/>Establecimientos, Servicios, Diccionario,<br/>Excepciones, Hojas, Versiones]
    G --> H[UPDATE Software.version]
    H --> I["Éxito: 'Finalizado Con Exito !!'"]
```

### Web Services Involucrados

| Servicio | Operación | Uso |
|----------|-----------|-----|
| WebService1 | `version()` | Versión servicio |
| versionejecutable | `VersionEjec()` | Versión ejecutable |
| webservicedb | `datos()`, `diccionario()` | Catálogos |
| CargaSerie | `serieA()` | Descarga por serie |
| CargaSerieServ | `DatosSerie()` | Datos por SS/año/mes |
| Optimiza_Up | `Datos_Up()` | Optimización remota |

---

## 8. Flujos Integrados

### Flujo Completo Carga datos

```mermaid
flowchart TB
    subgraph "M1 - Arranque"
        A[Iniciar app] --> B[Verificar SS seleccionado]
    end
    
    subgraph "M2 - Carga (ej: individual)"
        B --> C[Seleccionar archivo]
        C --> D[Validaciones]
        D --> E[Persistencia datos/errores]
        E --> F[Actualizar HistCarga]
    end
    
    subgraph "M3 - Revisión"
        F --> G[Ver LogError]
        F --> H[Ver InformeControl]
    end
    
    subgraph "M4 - Consulta"
        I[EstabCargados] --> J[Ver matriz cobertura]
        K[Duplicidad] --> L[Ver duplicados]
    end
    
    subgraph "M5 - Mantenimiento"
        F --> M[Eliminar datos]
        N[Optimiza] --> O[Compactar bases]
        P[Export] --> Q[Exportar DBF]
    end
    
    subgraph "M6 - Sincronización"
        A --> R[Actualizar catálogos]
        S[ActDBServ] --> T[Descargar datos WS]
    end
```

---

## 9. Diagramas ER Principales

### Entidad-Dato Principal

```mermaid
erDiagram
    DATOS {
        Long ano PK
        Long mes PK
        Text idestablecimiento PK
        Text codigoprestacion PK
        Double col01 ... col50
        DateTime fechacarga
    }
    
    LOGErr {
        Text archivo
        Text idestablecimiento
        Long mes, ano, serie
        Long linea
        Text hoja
        Text dato
        MEMO mensaje_error
    }
    
    HISTCARGA {
        Text IdEstablecimiento
        Long Mes, Ano
        Text Serie
    }
    
    DATOS ||--o{ LOGErr : "genera"
    DATOS ||--o{ HISTCARGA : "registra"
```

---

## 11. Diagramas Visuales por Módulo

### M1 - Arquitectura Visual

```mermaid
graph TD
    A[🖥️ ValidadorRem.exe] --> B[🎯 Principal<br/>Shell 13 botones]
    B --> C{👤 SS Seleccionado?}
    C -->|Sí| D[🔓 Módulos M2-M6 activos]
    C -->|No| E[🔒 Módulos bloqueados<br/>RN-G1]
    
    A --> F[🔄 M1 - Arranque]
    F --> G[🆕 Seteo - Selección SS]
    F --> H[🌐 WS - Verificación version]
    F --> I[📁 Archivos - Global.mdb, Trans.mdb]
    F --> J[📡 WebSocket - 2 consultas paralelas]
```

### M2 - Arquitectura Visual

```mermaid
graph TD
    A[📁 Usuario - Excel] --> B[📊 CargaDatos<br/>Wizard individual]
    A --> C[📁 CargaDatosMultiple<br/>Carpeta completa]
    
    B --> D[⚙️ CargaExcel Motor]
    C --> D
    
    D --> E[✅ datos tabla<br/>Registros validos]
    D --> F[❌ LogError tabla<br/>Errores capturados]
    D --> G[📊 HistCarga<br/>Histórico cargas]
    
    E --> H[📈 M4 - Consultas]
    F --> I[🔍 M3 - Revisión errores]
    G --> H
```

### M3 - Arquitectura Visual

```mermaid
graph LR
    A[M2 - Errores] --> B[📋 LogErrorCargados<br/>UI filtros]
    A --> C[📋 LogErrorMultiples<br/>UI archivo]
    A --> D[📊 InformeControl<br/>Puntos calidad]
    
    B --> E[📥 Exportar Excel]
    C --> E
    D --> F[📈 Cálculo puntos]
    D --> G[📤 Exportar reporte]
    
    B -.-> H[❓ Puntos pendiente<br/>Validación requerida]
```

### M4 - Arquitectura Visual

```mermaid
graph TD
    A[📊 Consultas] --> B[🏢 EstabCargados<br/>Matriz mensual]
    A --> C[🔍 Duplicidad<br/>Grupo por clave]
    A --> D[💊 Prestacion<br/>Búsqueda código]
    A --> E[📈 Comparativos<br/>Por año/mes]
    A --> F[📄 Informe1<br/>Report INF1]
    
    B --> G[📋 dgvMatriz]
    C --> H[📋 dgvDuplicados]
    D --> I[📋 dgvResultados]
    E --> J[📊 ColumnChart]
    F --> K[📤 Export Excel]
    
    G --> L[💾 BASE datos.mdb]
    H --> L
    I --> L
    J --> L
    K --> L
```

### M5 - Arquitectura Visual

```mermaid
graph TD
    A[🗑️ Eliminar] --> B[⚠️ Confirmar borrado]
    B --> C[🧹 Borrado cascada<br/>Base + Hist + LogError]
    
    D[⚡ Optimiza] --> E[🔄 Compactar .mdb]
    D --> F[Spectrum 422MB → Optimizado]
    
    G[📤 Export DBF] --> H[✅ Validar datos]
    G --> I[📁 Seleccionar meses]
    H --> J[📥 Generar .dbf]
    
    K[📦 MINSAL] --> L[📋 Consolidar datos]
    K --> M[🗜️ GZip]
    L --> N[📤 Archivo minsal.zip]
```

### M6 - Arquitectura Visual

```mermaid
graph TD
    A[🔄 Actualizador] --> B[🔍 Verificar versión]
    A --> C[📥 Descargar catálogos]
    
    B --> D[🌐 WebService1.version]
    B --> E[🖥️ VersionEjecutable]
    D --> F[⚖️ Comparar versiones]
    E --> F
    
    C --> G[1️⃣ Establecimientos]
    C --> H[2️⃣ Servicios]
    C --> I[3️⃣ Diccionario]
    C --> J[4️⃣ Excepciones]
    C --> K[5️⃣ Hojas]
    C --> L[6️⃣ Versiones]
    
    G & H & I & J & K & L --> M[💾 Global.mdb actualizado]
```

---

## 12. Tablas Principales del Sistema

| Tabla | Ubicación | Filas | Uso Principal |
|-------|-----------|-------|---------------|
| **datos** | DatosSERIE{serie}{año}.mdb | ~15,060 | Datos validados cargados |
| **LogError** | Trans.mdb | Variable | Registro de errores |
| **mpEstablecimiento** | Global.mdb | 3,207 | Validación establecimientos |
| **Diccionario** | Global.mdb | 185,526 | Mapeo celdas a prestaciones |
| **version** | Global.mdb | 939 | Versiones plantilla |
| **Thojas** | Global.mdb | 1,129 | Estructura hojas Excel |
| **HistCarga** | Trans.mdb | ~4,000 | Histórico cargas |
| **cargados** | Temporal.mdb | 602 | Matriz cobertura mensual |
| **Software** | Global.mdb | 1 | Versión instalación |

---

**Documento generado:** 2026-11-05 - Análisis estático ValidadorRem v3.12.000