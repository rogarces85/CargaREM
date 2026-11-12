# Decisiones pendientes de firma

Cuatro decisiones de F0 que bloquean el cierre del proyecto. Cada una trae una
recomendación técnica con su justificación, la consecuencia de adoptarla y qué
cambiaría si se rechaza.

Están **adoptadas técnicamente** para no detener el desarrollo: el sistema ya se
comporta como se describe aquí. Falta la firma del referente para que dejen de
ser propuestas.

---

## D5 — Matriz de series obligatorias

**Recomendación:** todo establecimiento activo exige **las seis series del MVP**
(A, BS, BM, D, P, F) desde el primer período. Las excepciones se cargan una por
una, con motivo, desde la matriz de series del portal.

**Por qué.** Arrancar con «todo se exige» es el error que se corrige hacia
abajo: si falta una serie, se ve en la matriz y alguien pregunta. Arrancar con
«nada se exige» es el error que no se ve: los envíos se aprueban incompletos y
el faltante aparece recién en la consolidación, cuando ya no se puede pedir.

**Consecuencia.** El primer mes todos los establecimientos aparecen en rojo en
las series que no entreguen. Es ruido, pero es ruido verdadero.

**Estado en el sistema.** El seeder L1 crea la matriz completa para los 7
establecimientos de prueba. Los 86 del catálogo real se cargan con la misma
regla desde la pantalla de matriz.

**Si se rechaza:** hay que definir qué serie exige cada tipo de establecimiento
(hospital, posta, CESFAM) y cargarlo antes del primer período real.

---

## Severidad de los códigos

**Recomendación:** adoptar la severidad tal como está implementada, que sigue el
catálogo del Módulo M7 con dos excepciones documentadas.

| Severidad | Códigos |
|---|---|
| **Bloqueante** | ENV-001 a 013, 015, 019, 024, 025, 029, 030 |
| **Advertencia** | ENV-017, 018, 020, 021, 022, 023, 026, 027, 028, 031 |

**Las dos excepciones, y por qué.**

- **ENV-014** (versión de plantilla) es bloqueante cuando el catálogo tiene la
  plantilla del período. Si el catálogo **no** la tiene, baja a advertencia:
  bloquear por una carencia del catálogo dejaría el portal inservible el mes en
  que el Minsal publica tarde.
- **ENV-016** (hoja faltante) es bloqueante cuando la plantilla declara hojas.
  Si no las declara, advierte: no se puede exigir lo que no se sabe.

**La regla general:** se bloquea cuando se sabe que está mal; se advierte cuando
no se puede saber. Fingir certeza con un bloqueo es tan malo como dejar pasar un
error.

**Si se rechaza:** hay que indicar, código por código, cuál cambia y con qué
criterio.

---

## D4 — Plazos y reglas de cierre

**Recomendación:**

| Regla | Valor propuesto |
|---|---|
| Plazo de entrega | **día 10** del mes siguiente al período |
| Cierre del período | **manual**, por el administrador, después del plazo |
| Envíos atrasados | se aceptan igual, marcados «fuera de plazo» |
| Reapertura | solo el administrador, con motivo |

**Por qué manual y no automático.** Un cierre automático el día 10 deja afuera a
quien estaba cargando a las 23:59, y obliga a reabrir. Con cierre manual, el
administrador cierra cuando el Servicio ya no espera nada —normalmente dos o
tres días después del plazo— y la decisión queda registrada con su nombre.

**Por qué se aceptan atrasados.** El objetivo del portal es que el dato llegue,
no castigar. Un envío fuera de plazo es información útil para la jefatura; un
envío rechazado es un dato que no existe.

**Estado en el sistema.** El modelo de períodos tiene estado (abierto, en
revisión, cerrado) y la fecha límite. Falta: el indicador visual de «fuera de
plazo» en la bandeja y en la matriz, y el bloqueo de cargas en un período
cerrado —que ya existe—.

**Si se rechaza:** hay que fijar otro plazo y decidir si el cierre es automático
o no. Lo segundo cambia código; lo primero no.

---

## D1 y D10 — Identidad y segundo factor

**Recomendación:**

| Rol | Cuenta | 2FA |
|---|---|---|
| Administrador | local, creada por otro administrador | **obligatorio** |
| Revisor | local, creada por el administrador | **obligatorio** |
| Encargado | local, creada por el administrador | opcional |

**Por qué local y no directorio institucional.** El servidor no tiene LDAP ni
SSO confirmado, y esperar esa integración deja el portal sin usuarios. Las
cuentas locales son reversibles: cuando exista directorio, se migra.

**Por qué 2FA obligatorio para admin y revisor.** Son los dos roles que pueden
aprobar o cambiar la configuración. Un encargado comprometido afecta a su
establecimiento; un administrador comprometido afecta a todo el Servicio.

**Por qué opcional para encargado.** Son decenas de personas rotando, muchas
con teléfono compartido. Exigirlo ahí es la forma más rápida de que el sistema
no se use.

**Estado en el sistema.** Fortify ya trae 2FA TOTP. Falta: hacerlo obligatorio
por rol —un middleware que redirija a la configuración de 2FA si el rol lo
exige y no está activo—.

**Si se rechaza:** si TI confirma que hay directorio, cambia el origen de las
cuentas pero no el resto del modelo.
