# Módulo M5 Profundizado: Mantenimiento y Exportación

Versión documentada: 1.0 | Estado: ✅ Implementado | Fecha: 2026-11-05

---

## 1. Arquitectura del Módulo

### Componentes Principales

| Componente | Tipo | Función Principal | Implementación |
|------------|------|---------------------|----------------|
| **Eliminar** | Formulario | Borrado selectivo de datos | ✅ |
| **Optimiza** | Formulario | Compactación bases Access | ✅ |
| **Export** | Formulario | Exportación a formato DBF | ✅ |
| **Minsal** | Formulario | Generación archivo MINSAL | ✅ |
| **bases** | Clase estática | Operaciones CRUD | ✅ |

---

## 2. Flujos de Datos Detallados

### D5.1: Flujo de Borrado de Datos (Eliminar)

```mermaid
flowchart TB
    subgraph "Entrada Usuario"
        A1[Usuario: Cb_ano → año]
        A2[Usuario: Cb_mes → mes]
        A3[Usuario: Cb_serie → serie]
        A4[Usuario: Cb_establecimiento → cod establecimiento]
    end
    
    subgraph "Validación selección"
        B1[¿ano == 0 OR mes == 0 OR serie == "" OR establecimiento == ""?]
        B2[Mensaje: "Error de Seleccion"]
    end
    
    subgraph "Confirmación"
        C1[Mensaje: "Esta seguro de Eliminar..."]
        C2[Botón: Sí / No]
    end
    
    subgraph "Borrado en cascada"
        D1[ bases.Elimina_Historico(establecimiento, mes, ano, serie)]
        D2[bases.borra_datos(establecimiento, mes, ano, serie)]
        D3[DELETE FROM LogError WHERE...]
    end
    
    subgraph "Salida"
        E1[Mensaje: "Se han eliminado los datos con exito"]
        E2[Mensaje: "Error de Seleccion"]
    end
    
    A1 & A2 & A3 & A4 --> B1
    B1 -->|Error| B2 & E2
    B1 -->|Correcto| C1
    C2 -- Sí --> D1 & D2 & D3
    D1 & D2 & D3 --> E1
```

### D5.2: Flujo de Optimización de Bases (Optimiza)

```mermaid
flowchart TB
    subgraph "Inicio"
        A1[Usuario: Click "Optimizar bases"]
    end
    
    subgraph "Enumeración archivos"
        B1[Directory.GetFiles(ruta, "*.mdb")]
        B2[Lista: Datos/*.mdb, Global.mdb, etc.]
    end
    
    subgraph "Proceso compactación"
        C1[Para cada archivo]
        C2[Crear archivo temporal ~...mdb]
        C3[JRO.CompactDatabase(origen, destino)]
        C4[File.Delete(archivo original)]
        C5[File.Move(temp, archivo)]
    end
    
    subgraph "Mensajes UI"
        D1["'Optimizando DB : ...'"]
        D2["'Actualizando......'"]
        D3["'.....Ok'"]
        D4["'Optimización satisfactoria'"]
    end
    
    subgraph "Salida"
        E1[Archivo compactado]
    end
    
    A1 --> B1 --> C1
    C1 --> C2 --> C3 --> C4 --> C5 --> D1 --> D2 --> D3 --> E1
    E1 --> D4
```

### D5.3: Flujo de Exportación a DBF (Export)

```mermaid
flowchart TB
    subgraph "Entrada Usuario"
        A1[Usuario: Cb_ano → año]
        A2[Usuario: Chk_mes1...12 → meses seleccionados]
        A3[Usuario: txtRuta → ruta archivo DBF]
        A4[Usuario: btnBrowse → navegar]
    end
    
    subgraph "Validaciones"
        B1[¿Existen datos para filtro?]
        B2[¿Ruta válida y accesible?]
        B3[¿Al menos un mes seleccionado?]
    end
    
    subgraph "Generación"
        C1[SELECT * INTO [dBase III; DATABASE=...]]
        C2[FROM datos WHERE mes IN (...) AND ano = ...]
    end
    
    subgraph "Salida"
        D1[Mensaje: "Generación de Archivo Exitoso"]
        D2[Archivo DBF en ruta]
    end
    
    subgraph "Mensajes error"
        E1["'No Existen datos...'"]
        E2["'Por Favor Ingrese Ruta'"]
        E3["'Por Favor, Debe elegir Mes'"]
    end
    
    A1 --> B1
    A2 --> B1
    B1 -->|Sin datos| E1
    B1 -->|Correcto| B2 & B3
    A3 --> B2
    B2 -->|Sin ruta| E2
    B3 -->|Sin mes| E3
    B2 --> C1
    B3 --> C1
    A4 --> C1
    C1 --> C2 --> D1 & D2
```

### D5.4: Flujo de Generación Archivo MINSAL (Minsal)

```mermaid
flowchart TB
    subgraph "Entrada Usuario"
        A1[Usuario: Cb_ano → año]
        A2[Usuario: Cb_mes(es) → meses]
        A3[Usuario: txtRuta → ruta salida]
    end
    
    subgraph "Preparación"
        B1[string rutaTemp = Path.Combine(Path.GetTempPath(), "MinsalTemp.mdb")]
        B2[File.Copy("MASTER.mdb", rutaTemp, true)]
    end
    
    subgraph "Inserción datos por serie"
        C1[Para cada serie (A, BS, BM, D, P, F)]
        C2[INSERT INTO [Datos] SELECT * FROM DatosSERIE{serie}{año}.mdb.Datos]
        C3[INSERT INTO [DatosC] SELECT * FROM ...]
    end
    
    subgraph "Registro estadísticas"
        D1[INSERT INTO registros (Ano, CantReg, Fecha, idservicio, Mes, Serie)]
        D2[SELECT año, COUNT(*), NOW(), @idservicio, mes, 'A' FROM Datos]
    end
    
    subgraph "Compresión"
        E1[using FileStream + GZipStream]
        E2[byte[] buffer = Encoding.UTF8.GetBytes(reader.ReadToEnd())]
        E3[gzip.Write(buffer, 0, buffer.Length)]
    end
    
    subgraph "Limpieza"
        F1[File.Delete(rutaTemp)]
    end
    
    subgraph "Salida"
        G1[Mensaje: "Se ha generado el archivo Exitosamente"]
        G2[Archivo .zip/gz comprimido]
    end
    
    A1 --> B1
    B1 --> B2 --> C1
    C1 --> C2 & C3 --> D1 & D2
    D1 & D2 --> E1 --> E2 & E3 --> F1 --> G1 & G2
```

### D5.5: Diagrama ER de Operaciones

```mermaid
erDiagram
    DATOS_SERIE {
        Long ano PK
        Long mes PK
        Text idestablecimiento PK
        Text codigoprestacion PK
        Double col01 ... col50
        DateTime fechacarga
    }
    
    TEMPORAL_INCORRECTO {
        Long ano PK
        Long mes PK
        Long idservicio
        Text idestablecimiento
        Text codigoprestacion
        Double col01 ... col50
        DateTime fechacarga
        MEMO motivo_error
    }
    
    MASTER {
        Text codigo
        Text glosa
        Double col01 ... col50
    }
    
    REGISTROS {
        Long Ano
        Long CantReg
        DateTime Fecha
        Long idservicio
        Long Mes
        Text Serie
    }
    
    DATOS_SERIE ||--o{ TEMPORAL_INCORRECTO : "puede generar"
    MASTER ||--o{ REGISTROS : "contiene"
    DATOS_SERIE ||--o{ MASTER : "se exporta a"
```

---

## 3. Reglas de Negocio Detalladas

| Código | Nombre | Condición | Acción | Estado |
|--------|--------|-----------|--------|--------|
| **RN-M5-1** | Borrado en Cascada | Antes de recarga | Borrar datos, histórico, errores | ✅ Implementado |
| **RN-M5-2** | Exportación con Validación | Sin datos/ruta/mes | Mostrar mensajes de error | ✅ Implementado |
| **RN-M5-3** | Compactación con Manejo | Archivo bloqueado | Error, sin retry | ✅ Parcial |
| **RN-M5-4** | Generación MINSAL | Sin datos/mes/serie | Mensajes específicos | ✅ Implementado |

---

## 4. Estructura de Archivos Involucrados

```text
Instalación/
├── MASTER.mdb           (Plantilla base)
├── MasterC.mdb          (Plantilla con datos C)
├── MasterDB.mdb         (Plantilla base datos)
├── Global.mdb           (422 MB - Catálogos)
├── Trans.mdb            (Histórico, SetServicio)
├── Datos/
│   ├── DatosSERIEA{ano}.mdb
│   ├── DatosSERIEBS{ano}.mdb
│   ├── DatosSERIEBM{ano}.mdb
│   ├── DatosSERIED{ano}.mdb
│   ├── DatosSERIEP{ano}.mdb
│   └── DatosSERIEF{ano}.mdb
├── Control/
│   ├── ControlSERIEA{ano}.mdb
│   ├── ControlSERIEBS{ano}.mdb
│   └── ...
└── Temporal/
    ├── Temporal.mdb
    ├── TemporalIncorrectos.mdb
    ├── TemporalControl.mdb
    └── TemporalInforme1.mdb
```

---

## 5. Mensajes de UI

| Estado | Mensaje | Componente |
|--------|---------|------------|
| Éxito borrado | "Se han eliminado los datos con exito" | Eliminar |
| Error selección | "Error de Seleccion" | Eliminar |
| Confirmación | "Esta seguro de Eliminar el establecimiento..." | Eliminar |
| Inicio optimización | "Optimizando DB : ..." | Optimiza |
| Progreso | "Actualizando......" | Optimiza |
| Éxito optimización | ".....Ok" | Optimiza |
| Final optimización | "Optimización satisfactoria" | Optimiza |
| Sin datos DBF | "No Existen datos para este año y serie." | Export |
| Sin ruta | "Por Favor Ingrese Ruta de Archivo" | Export |
| Sin mes | "Por Favor, Debe elegir Mes" | Export |
| Éxito DBF | "Generación de Archivo Exitoso" | Export |
| Sin datos MINSAL | "No Existen datos para este año y serie." | Minsal |
| Sin año | "Por Favor, Ingrese Año" | Minsal |
| Sin serie | "Por Favor, Ingrese Serie" | Minsal |
| SIN MES | "Por Favor, Debe elegir Mes" | Minsal |
| Sin ruta | "Por Favor Ingrese Ruta de Archivo" | Minsal |
| Éxito MINSAL | "Se ha generado el archivo Exitosamente" | Minsal |

---

## 6. Consideraciones Técnicas

### Tecnologías utilizadas

- **Compactación:** JRO.IJetEngine.CompactDatabase (Jet/ACE 32-bit)
- **Exportación:** ADO SELECT INTO dBase III
- **Compresión:** System.IO.Compression.GZipStream
- **Excel:** Microsoft.Office.Interop.Excel 14.0

### Problemas identificados

| Error | Causa | Solución |
|-------|-------|----------|
| "No se pudo bloquear el archivo" | Archivo en uso | Cerrar procesos, usar Optimiza después |
| "No Existen datos..." | Filtro sin resultados | Verificar año, serie, establecimientos |
| Error permisos | Sin escritura | Verificar permisos de usuario |
| Error COM Excel | Office no instalado | Instalar Excel requerido |

---

## 7. Estado de Validación Pendiente

| Elemento | Estado | Comentario |
|----------|--------|------------|
| Formato exacto archivo MINSAL | ❌ Pendiente | Contenido interno, extensión, nombre |
| Efecto `generacion = False` | ❌ Pendiente | Significado exacto no documentado |
| Procesos alternativos lock | ❌ Pregunta | Qué mantiene archivos bloqueados |
| Rollback compactación | ❌ Pregunta | Manejo si falla |
| Límite tamaño DBF | ❌ Pregunta | Tamaño máximo exporte |

---

## 8. Métricas de Flujo

| Métrica | Valor | Comentario |
|---------|-------|------------|
| Tamaño Global.mdb | 422 MB | Base más grande |
| Archivos datos por año | 6-12 | Según series activas |
| Tiempo compactación | 2-5 min | Depende del tamaño |
| Tamaño exporte DBF | Variable | Similar al origen |
| Operaciones borrados | 3 (datos, hist, error) | - |

---

## 9. Arquitectura Web Propuesta

```http
# Endpoint para borrado
DELETE /api/datos?ano=2026&mes=9&serie=A&establecimiento=123456
Headers: { 'X-Confirm-Delete': '123456_2026_9_A' }
Response: { "success": true, "deletedRecords": 150, "historyCleared": true }

# Endpoint para optimización
POST /api/mantenimiento/optimizar
Body: { "tipo": "global|serie", "ano": 2026, "serie": "A" }
Response: { "success": true, "filesOptimized": 6, "spaceRecovered": "2.3 MB" }

# Endpoint para exportación DBF
POST /api/exportar/dbf
Body: { "ano": 2026, "meses": [1,2,3], "ruta": "base64" }
Response: { "downloadUrl": "https://cdn.example.com/file.dbf" }

# Endpoint para archivo MINSAL
POST /api/exportar/minsal
Body: { "ano": 2026, "meses": [1,2], "formato": "gzip" }
Response: { "downloadUrl": "https://cdn.example.com/minsal.zip" }
```

---

**Documento generado:** Basado en análisis estático. Requiere validación de formatos de salida y comportamiento exacto.