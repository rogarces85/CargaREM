# Módulo M1 Profundizado: Arranque y Configuración

Versión documentada: 1.0 | Estado: ✅ Implementado | Fecha: 2026-11-05

---

## 1. Arquitectura del Módulo

### Componentes Principales

| Componente | Tipo | Propósito | Implementación |
|------------|------|-----------|----------------|
| **Principal** | Formulario | Shell principal con menú (13 botones) | ✅ |
| **primera** | Formulario | Splash de bienvenida primera ejecución | ✅ |
| **Cargando** | Formulario | Indicador de actividad en background | ✅ |
| **Seteo** | Formulario | Diálogo selección Servicio de Salud | ✅ |
| **buscaActualizacion** | Formulario | Splash durante consulta actualizaciones | ✅ |

---

## 2. Flujos Detallados

### F1.1: Flujo de Arranque e Inicialización

```mermaid
flowchart TD
    A[Inicio aplicación<br/>STAThread] --> B[Splash: 'Cargando...']
    B --> C[buscaActualizacion<br/>'Buscando actualizaciones...']
    C --> D[Principal.Form1_Load]
    D --> E{Actualizado.txt existe?}
    E -- Sí --> F[Actualizacion.actualizar<br/>y delete archivo]
    E -- No --> G[Verificar conectividad WS]
    G --> H[WebService1.version()]
    G --> I[VersionEjecutable.VersionEjec()]
    H --> J[Comparar versión WS]
    I --> J
    J --> K{¿Versión diferente?}
    K -- Sí --> L[Mensaje: 'Actualizaciones disponibles']
    K -- No --> M[Mostrar Principal]
    L --> M
    M --> N{¿SS seleccionado?}
    N -- Sí --> O[Operaciones habilitadas]
    N -- No --> P[Operaciones bloqueadas<br/>msg RN-G1]
```

### F1.2: Verificación de Versión en Arranque

**Ubicación:** `Principal.Form1_Load` (método evento)

**Código implementado:**

```csharp
// Paso 1: Verificación de archivo de actualización
if (File.Exists("\\Actualizado.txt"))
{
    Actualizacion.actualizar();
    File.Delete("\\Actualizado.txt");
}

// Paso 2: Consultas WS en paralelo
// - version.WebService1.version() → string
// - VersionEjecutable.VersionEjec() → DataSet

// Paso 3: Comparación de versiones
if (Operators.CompareString(versionWS, versionLocal, false) != 0)
{
    MessageBox.Show("Existen Actualizaciones para la BD del programa...");
}

// Paso 4: Persistencia local
SELECT version FROM software WHERE nombre = 'CargaRem'
```

**Mensajes críticos:**
| Mensaje | Uso |
|---------|-----|
| `"Existen Actualizaciones para la BD del programa, dirijase al modulo de 'Actualizar'"` | Versión incompatible |
| `"Error de conexión"` / `"Error de Conexion"` | Sin conectividad |
| `"Ud.Ya Tiene la Última versión"` | Versión actual |

### F1.3: Selección de Servicio de Salud (SS)

**Ubicación:** Formulario `Seteo`

**Proceso paso a paso:**

```sql
-- Verificación de existencia previa
SELECT COUNT(Nservicio) FROM SetServicio

-- Si resultado > 0:
-- Mensaje: "Disculpe, Ud. ya Selecciono un Servicio de Salud"

-- Carga de lista de servicios
SELECT idservicio & ' - ' & nservicio AS descripcion,
       idservicio, nservicio
FROM mpservicio 
ORDER BY orden

-- Registro de selección
INSERT INTO SetServicio (Idservicio, Nservicio) 
VALUES (@idservicio, @nservicio)
```

**Validaciones aplicadas:**
- Una sola selección permitida por instalación (RN-G2)
- Requerida para operaciones M2-M6 (RN-G1)
- Bloqueo de reemplazo sin eliminación previa de `SetServicio`

---

## 3. Estructuras de Datos

### Tabla SetServicio

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `Idservicio` | Long (3) | Identificador único del Servicio de Salud |
| `Nservicio` | WChar (130) | Nombre descriptivo del Servicio de Salud |

### Tabla Software (Global.mdb)

| Campo | Valor | Uso |
|-------|-------|-----|
| `nombre` | 'CargaRem' | Identificador del software |
| `version` | string | Version actual instalada |

---

## 4. Reglas de Negocio

| Código | Nombre | Condición | Acción | Mensaje | Estado |
|--------|--------|-----------|--------|---------|--------|
| **RN-M1-1** | Actualización Obligatoria | Versión servicio ≠ versión local | Mostrar mensaje | "Existen Actualizaciones..." | ✅ Implementado |
| **RN-M1-2** | Protección SS | Ya existe record en SetServicio | Bloquear selección | "Disculpe, Ud. ya Selecciono..." | ✅ Implementado |

---

## 5. Web Services Involucrados

| Servicio | URL | Operación | Parámetros | Retorno |
|----------|-----|-----------|------------|---------|
| `WebService1` | https://cargasrem.minsal.cl/webservice/ | `version()` | - | string |
| `versionejecutable` | https://cargasrem.minsal.cl/webservice/ | `VersionEjec()` | - | DataSet |

---

## 6. Archivos de Configuración

### ValidadorRem.exe.config

```xml
<configuration>
  <appSettings>
    <!-- Rutas de servicios web -->
    <add key="WebServiceURL" value="https://cargasrem.minsal.cl/webservice/" />
    <add key="PruebaURL" value="http://intradeis.minsal.cl/webservicePrueba/" />
    <add key="generacion" value="False" />
  </appSettings>
</configuration>
```

---

## 7. Diagrama de Estado

```mermaid
stateDiagram-v2
    [*] --> Inicializacion
    Inicializacion --> VerificacionVersion
    VerificacionVersion --> SinConexion
    VerificacionVersion --> ConConexion
    ConConexion --> Principal
    SinConexion --> Principal
    
    state VerificacionVersion {
        [*] --> ConsultaWS
        ConsultaWS --> Comparacion
        Comparacion --> VersionCompatible
        Comparacion --> VersionIncompatible
    }
    
    state Principal {
        [*] --> EsperaSSeleccionado
        EsperaSSeleccionado --> OperacionesBloqueadas
        EsperaSSeleccionado --> OperacionesHabilitadas
    }
```

---

## 8. Mensajes de UI

| Estado | Mensaje |
|--------|---------|
| Arranque | "Cargando..." |
| Buscando actualización | "Buscando actualizaciones, espere por favor....." |
| Versión incompatible | "Existen Actualizaciones para la BD del programa, dirijase al modulo de 'Actualizar'" |
| Última versión | "Ud.Ya Tiene la Última versión" |
| SS ya seleccionado | "Disculpe, Ud. ya Selecciono un Servicio de Salud" |
| Selección exitosa | "Los Datos Fueron Ingresados Satisfactoriamente" |
| Sin conectividad | "Error de conexión" |
| Excepción | "Ha ocurrido una excepción no controlada" |

---

## 9. Consideraciones Técnicas

### Stack tecnológico
- **Runtime:** .NET Framework 4.8, IL-only, x86
- **UI:** WinForms (System.Windows.Forms)
- **Persistencia:** OleDb (Jet/ACE 32-bit)
- **Web Services:** SOAP ASMX (System.Web.Services)

### Puntos críticos de implementación
1. Hilo principal requiere STAThreadAttribute
2. Consultas paralelas a 2 WebSockets
3. No hay bucle de reintento
4. SS persiste en Trans.mdb

---

## 10. Errores y Manejo

| Escenario | Mensaje | Comportamiento |
|-----------|---------|----------------|
| Timeout/red | "Error de conexión" | Continúa en modo local |
| HTTP 401/403 | No manejado explícitamente | Mensaje genérico |
| HTTP 404/500 | No manejado explícitamente | Mensaje genérico |
| Excepción | "Ha ocurrido una excepción no controlada" | Escrito en DetalleExcepcion.txt |

---

## 11. Estado de Validación Pendiente

| Elemento | Estado | Comentario |
|----------|--------|------------|
| Código exacto de manejo HTTP | ❌ Pendiente | No se observó manejo explícito de códigos |
| Reintentos WS | ❌ Pendiente | No hay bucle de reintento observable |
| Efecto `generacion = False` | ❌ Pendiente | Significado exacto no documentado |
| Manejo de múltiples SS | ❌ Pregunta | Proceso de cambio/exclusión no definido |
| Actualización obligatoria | ❌ Pregunta | ¿Bloqueante o informativo? |

---

## 12. Métricas

- **Líneas IL estimadas:** `Principal.Form1_Load`: ~400-500 líneas
- **Líneas IL Seteo:** ~150-200 líneas
- **Consultas WS:** 2 operaciones (version, VersionEjec)
- **Consultas SQL:** 2 SELECT, 1 INSERT
- **Componentes formularios:** 5

---

**Documento generado:** Basado en análisis estático del binario `ValidadorRem.exe` (PE32 x86, .NET Framework 4.8). Requiere validación con ejecución en entorno de prueba para confirmar flujos exactos.

---

## 13. Próximos Pasos

### Módulos a profundizar:
1. **M2 - Carga de Datos** (objetivo actual)
2. M3 - Errores y Control
3. M4 - Consulta e Informes
4. M5 - Mantenimiento y Exportación
5. M6 - Sincronización

### Al finalizar:
- [ ] Crear documento único de Flujo de Datos integral del sistema
- [ ] Mapear dependencias entre módulos
- [ ] Documentar APIs/Web Services restantes