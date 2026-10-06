# Módulo — Establecimientos y Seguros médicos

**Producto:** MeeMedico.com
**Fase:** 1 (backoffice). Las páginas públicas y la importación masiva de estos datos van en fases posteriores (§9).
**Versión:** 1.0
**Fecha:** octubre 2026
**Estado:** Aprobado. Decisiones del §10 cerradas en octubre de 2026.
**Esquema:** `DATABASE.md` §9.9–§9.14 · **Conceptos:** `MODELO-DOMINIO.md` §2.12–§2.14

---

# 1. Para qué sirve este documento

Deja por escrito **qué se construye, por qué y en qué orden** en los dos módulos nuevos del backoffice:

1. **Establecimientos:** hospitales, clínicas, centros médicos y centros de salud, tanto públicos como privados, con los médicos que atienden en cada uno.
2. **Seguros médicos:** las aseguradoras de cada país y los médicos que atienden por cada una.

Aquí no hay SQL. El esquema vive en `DATABASE.md` y los conceptos en `MODELO-DOMINIO.md`, igual que en el resto del proyecto. Este documento recoge el razonamiento, las pantallas y el plan.

---

# 2. Cambio de alcance respecto a lo aprobado

Hasta ahora, la entidad Clínica estaba planificada para **V2** (`MODELO-DOMINIO.md` §10, `DATABASE.md` §18). Los seguros no aparecían en ninguna fase.

**Decisión (octubre 2026):** ambos entran en Fase 1, **solo como datos del directorio que gestiona el administrador**.

| Dentro de Fase 1 | Fuera, para una fase posterior |
|---|---|
| Establecimientos, sus sedes, contactos y red operadora | Cuentas de usuario de clínica, y que la clínica gestione su plantilla |
| Aseguradoras por país | Claim de establecimientos |
| Médicos ↔ establecimiento, médicos ↔ aseguradora, establecimiento ↔ aseguradora | Planes de seguro, convenios, copagos y precios |
| Campos de base de datos necesarios para la futura landing del establecimiento | Las páginas públicas (landing del establecimiento, filtro por seguro) |
| Pantallas de Filament, solo para admin | Importación masiva desde Excel de establecimientos y seguros |
| | Laboratorios y centros de imágenes |
| | Horarios, servicios, procedimientos y reviews de establecimientos |

**Por qué adelantarlo no rompe la disciplina de fases:**

- La entidad ya estaba prevista: `doctor_locations.location_type` admite `clinic` y `hospital`, y la evolución `Doctor → Clínica → Ubicación` estaba documentada. Lo que se adelanta es esa evolución, no algo nuevo.
- Cierra una decisión que estaba pendiente: la dirección compartida por varios médicos pasa a pertenecer al establecimiento (`DATABASE.md` §9.6).
- No toca el núcleo. `doctors` no recibe columnas nuevas, y la puerta de publicación del médico no cambia.

---

# 3. Referencias: qué hacen otras plataformas

| Plataforma | Cómo trata establecimientos | Cómo trata seguros | Qué tomamos |
|---|---|---|---|
| **Doctoralia** (LATAM y España) | Página propia de cada centro médico, con dirección, mapa y la lista de especialistas que atienden ahí, filtrable por especialidad | El médico declara las aseguradoras que acepta, **por cada consultorio**. El buscador filtra por aseguradora | La landing del establecimiento es básicamente "sus médicos agrupados por especialidad", así que la relación médico ↔ establecimiento es el núcleo del módulo. Los seguros se modelan por médico (§5.6 explica por qué no por consultorio) |
| **Zocdoc** (EE. UU.) | Agrupa a los profesionales por consulta | **Aseguradora → plan.** El seguro es el filtro principal del buscador. Mantener esos datos al día es su mayor esfuerzo operativo, y lo apoya en el propio médico | No modelamos planes: sin un médico que los mantenga (claim), los datos se quedan viejos enseguida. El nivel de plan se podrá añadir después sin romper nada (§9) |
| **Top Doctors** | Páginas de hospitales y centros con sus especialistas. Las usa como páginas de autoridad SEO para búsquedas de marca ("Hospital X") | Filtro por aseguradora | Las búsquedas de marca de un hospital son tráfico real. Por eso el slug, la descripción y el logo del establecimiento se modelan desde ahora, aunque la página se construya más adelante |

**Lo que nos diferencia:** la mezcla de redes públicas y privadas. Ninguna de las tres plataformas modela bien una red como la CCSS, el IGSS, el SNS o el IVSS, que es a la vez institución operadora de hospitales y, en algunos países, el seguro social. En LATAM eso es una parte enorme de la atención, y por eso existe la entidad **Red** (§5.3).

---

# 4. Modelo resumido

```
Country ─┬─ N FacilityNetwork (red: CCSS, IGSS, grupo privado)
         │        │ 0..1
         │        ▼
         ├─ N Facility (hospital, clínica, centro médico, centro de salud)
         │        │ 1:N                  │ N:N
         │        ▼                      ▼
         │     Location (sede) ◄─ N:N ─ Doctor ─ N:N ─► Insurer
         │                                                 ▲
         ├─ N Insurer ─────────────────────────────────────┘
         │
         └─ Facility ─ N:N ─► Insurer   (convenios del establecimiento, §10.1)
```

Las tablas y restricciones están en `DATABASE.md` §9.9–§9.14.

---

# 5. Decisiones

Cada decisión se tomó comparando alternativas, siguiendo el criterio de `AGENTS.md`.

## 5.1 El establecimiento es dueño de sus sedes; el médico se vincula a la sede

**Opciones:**

- **A (elegida):** `locations.facility_id`. El establecimiento posee sus sedes. El médico se sigue vinculando a la sede (`doctor_locations`), como hoy, y "médicos del establecimiento" se **deriva** de ahí.
- **B:** una tabla N:N `doctor_facilities`, independiente de las ubicaciones.

**Por qué A:**

- No duplica datos. Con B, un médico puede figurar "en el Hospital X" mientras su ubicación dice otra cosa, y las dos fuentes divergen.
- Es la evolución que ya estaba documentada.
- Resuelve la dirección compartida: una ubicación que pertenece a un establecimiento solo la edita un admin.
- Asociar un médico a un establecimiento sigue siendo una sola acción en el backoffice: elegir el médico y la sede.

**Coste aceptado:** no se puede representar a un médico "afiliado" a un hospital sin consultorio en él (por ejemplo, con privilegios quirúrgicos pero atendiendo en otra parte). Si llega a hacer falta, se añade una relación de afiliación sin tocar lo anterior.

## 5.2 Un establecimiento, un país; un tipo y un sector

- **Tipos:** `hospital`, `clinic` (clínica), `medical_center` (centro o torre médica con consultorios), `health_center` (atención primaria pública: EBAIS, puestos del IGSS, UNAP, ambulatorios).
- **Sector:** `public`, `private` o `mixed`. Se incluye `mixed` porque existe el caso real de servicios públicos operados por terceros, como las cooperativas que prestan servicios a la CCSS.
- **Sedes en varias ciudades:** un establecimiento puede tener varias sedes, todas en su mismo país, y la base de datos lo garantiza. **Criterio operativo:** si cada sede tiene identidad propia ante el paciente ("Hospital CIMA San José" y "Hospital CIMA Guanacaste"), se registran como dos establecimientos de la misma red. Si es una sola marca con sucursales, va como un establecimiento con varias sedes.

## 5.3 Red operadora como catálogo opcional

Un atributo `sector` no basta para responder "todos los hospitales de la CCSS". La **Red** (`facility_networks`) es un catálogo pequeño por país: CCSS, IGSS, MSPAS, SNS, IVSS, o un grupo privado. El establecimiento puede pertenecer a una red o a ninguna.

**Duplicidad aceptada:** la CCSS aparece como red y también puede aparecer como aseguradora pública. Son dos papeles distintos de la misma institución. Unificarlos en una sola entidad complicaría los dos módulos para resolver un problema que hoy no existe.

## 5.4 Contactos del establecimiento como entidad

Siguen el mismo patrón que los contactos del médico: tipo, valor normalizado, etiqueta ("Emergencias", "Citas"), visibilidad y principal. Un contacto puede ser general o de una sede concreta. Así la landing futura puede mostrar teléfonos y el registro de llamadas puede extenderse a establecimientos sin rediseñar nada.

## 5.5 Aseguradora por país, solo a nivel de aseguradora

- **Por país**, no global. BMI o Pan-American Life operan en varios países, pero con redes distintas en cada uno. Si se necesitara agruparlas, se añadiría un grupo por encima sin tocar lo existente.
- **Sin planes.** Ver Zocdoc en el §3.
- **Tipo** `private` o `public`. Cada país decide si su seguridad social figura como aseguradora: SeNaSa en RD sí, porque funciona como una ARS más. La CCSS en CR normalmente no, porque se modela como red de establecimientos.

## 5.6 El seguro va por médico, no por médico + sede

Doctoralia lo hace por consultorio. Nosotros lo hacemos **por médico**, porque multiplica por el número de sedes el trabajo de carga, y la fuente de esos datos (el médico, tras el claim) todavía no existe. Pasar a nivel de sede más adelante es añadir una columna nullable `location_id`; no rompe nada.

## 5.7 Seguros del establecimiento (convenios)

"¿Qué hospitales aceptan mi seguro?" es una consulta tan frecuente como la del médico, y la landing del establecimiento la mostrará. Se modela como una N:N `facility_insurers`, independiente de los seguros de cada médico. **Confirmado** (§10.1).

## 5.8 Publicación del establecimiento

El establecimiento tiene `status` `draft`, `active` o `inactive`, con una puerta de calidad mínima: **≥1 sede activa y ≥1 contacto público**. Mientras no existan las páginas públicas, `active` solo significa "listo para publicar". Así, el día que se construya la landing, el conjunto publicable ya está depurado.

**Lo que no cambia:** la puerta de publicación del médico. No se le exige tener establecimiento ni seguro.

## 5.9 Slugs

- El slug del establecimiento es único **por país y entre todos los tipos**. La URL será `/{pais}/clinicas/{slug}` para todos los tipos (§10.2), así que cambiar el tipo de un establecimiento nunca cambia su URL.
- Las aseguradoras también llevan slug, porque es la base de las páginas de filtro del tipo "cardiólogos que aceptan BMI".
- Ningún slug cambia sin dejar redirección: `slug_redirects` admite las entidades `facility` e `insurer`.

---

# 6. Reglas de dominio (Actions)

Todas viven en `app/Domain/Directory/Actions/`, nunca en recursos de Filament.

| Action | Regla |
|---|---|
| `CreateFacilityAction` | Genera un slug único por país. La red, si la hay, es del mismo país y está activa |
| `UpdateFacilityAction` | Registra los cambios en `activity_log` con sus valores anteriores. Si cambia el slug, pasa por `UpdateSlugAction` |
| `PublishFacilityAction` / `UnpublishFacilityAction` | Puerta del §5.8. Hoy no purga páginas del establecimiento porque no existen; ese punto de enganche queda marcado en el código |
| `FacilityLocationsAction` | Crea una sede o asigna una ubicación existente. Exige el mismo país. Una ubicación de otro establecimiento no se reasigna en silencio: hace falta una acción explícita de "mover". Al quitar una sede, desvincula antes los contactos de esa sede |
| `FacilityContactsAction` | Normaliza a E.164 o a minúsculas y garantiza un solo contacto principal por tipo. El contacto de una sede exige que la sede pertenezca al establecimiento |
| `FacilityInsurersAction` | Exige una aseguradora activa del mismo país |
| `DoctorInsurersAction` | Exige una aseguradora activa del país del médico. Usa `DoctorAggregateChange`: se audita como `doctor.updated` con `part = insurers` y purga la ficha |
| `SetCatalogStatusAction` | Se extiende a aseguradoras y redes. Desactivar no rompe los vínculos existentes; solo deja de ofrecerse como opción. Tampoco deja desactivar la única sede activa de un establecimiento activo |

**Detalles fijados al implementar la Etapa 2:**

- **Un establecimiento activo nunca queda incompleto.** `FacilityAggregateChange` aplica cada cambio de sedes o contactos, comprueba la puerta (`FacilityPublicationRequirements::guard`) y, si fallaría, revierte. Es el mismo patrón que `DoctorAggregateChange` con las fichas publicadas.
- **La puerta del establecimiento no exige ciudad activa,** a diferencia de la del médico: el establecimiento no genera listados por ciudad.
- **Al crear, el sector se hereda de la red** si no se indica.
- **El país y el slug no se editan** en `UpdateFacilityAction`. El slug va por `UpdateSlugAction`, que ahora también impide que un establecimiento se quede con la URL antigua de otro, igual que con los médicos.
- **Mover una sede deja sus contactos** como contactos generales del establecimiento de origen: no viajan con la dirección.
- **Código compartido:** `ContactNormalizer` (lo usan los contactos de médicos y de establecimientos) y `EntitySlugGenerator` (establecimientos ahora, aseguradoras en la Etapa 4).

**Purga de CDN.** Cuando la ficha pública del médico muestre "Atiende en Hospital X" y sus seguros:

- Renombrar o desactivar un establecimiento o una aseguradora tiene que purgar las fichas de los médicos vinculados.
- `DoctorCachePurge` ya calcula esas rutas, así que se reutiliza.
- Se implementa en este módulo para que nunca haya una ventana con fichas desactualizadas en caché.

**`MergeDoctorsAction`** (todavía sin implementar) debe mover también `doctor_insurers`. Está reflejado en `DATABASE.md` §13.

---

# 7. Pantallas del backoffice

Solo el rol `admin`. Los permisos nuevos están en `DATABASE.md` §16.2 y, como siempre, la lista canónica es `PermissionSeeder`.

## 7.1 Directorio → Establecimientos (nuevo)

- **Listado:** filtros por país, tipo, sector, red y estado. Columnas de número de sedes y número de médicos, y una acción en lote para activar o desactivar.
- **Ficha, por pestañas:**
  - **Datos:** nombre, tipo, sector, red, descripción y logo (el logo, pendiente: §7.4).
  - **Sedes:** crear una sede nueva, o asignar una ubicación existente con sugerencias por nombre y dirección parecidos en la misma ciudad. Es la forma de absorber las ubicaciones de tipo clínica que ya cargó el importador.
  - **Contactos.**
  - **Seguros** (convenios, §5.7).
  - **Médicos:** lista derivada de las sedes, con especialidad principal y sede. Botón **"Asociar médico"**: elegir médico, sede y modalidad (`office` / `clinic` / `hospital`), que pasa por `DoctorLocationsAction`.
  - **Historial.**
- **Checklist de publicación**, igual que en el médico.

## 7.2 Directorio → Aseguradoras (nuevo)

- **Listado:** filtros por país, tipo y estado, con el número de médicos de cada una.
- **Ficha:** datos y una pestaña **Médicos** para vincular y desvincular.
- **Carga a escala:** acción en lote **"Asignar seguro"** en el listado de Médicos. Es el caso real: una aseguradora entrega su red de médicos y el admin los filtra y asigna de una vez.

## 7.3 Directorio → Redes (nuevo, catálogo simple)

Gestión en modal, como Regiones: país, nombre, sigla, sector y estado.

## 7.4 Detalles fijados al implementar la Etapa 3

- **Sedes, tres caminos separados:**
  - "Nueva sede".
  - "Usar una dirección existente": solo direcciones sueltas del mismo país.
  - "Traer de otro establecimiento": la acción explícita de mover, con confirmación.
  Las sugerencias son la búsqueda sin acentos por nombre y dirección (`LocationSearch`).
- **"Asociar médico" propone la modalidad según el tipo** de establecimiento:
  - hospital → Hospital;
  - clínica y centro de salud → Clínica;
  - centro médico → Consultorio.
  Si hay una sola sede activa, también la propone.
- **"Desvincular" quita al médico de todas las sedes del establecimiento, o de ninguna.** Si es una ficha publicada y no tiene otra ubicación, se rechaza con el aviso de siempre.
- **El listado** cuenta sedes y médicos, busca sin acentos, tiene pestañas por estado y permite activar y desactivar en lote (hasta 100 por vez).
- **El logo todavía no se puede subir.** La columna `logo_path` existe, pero subir archivos exige decidir el almacenamiento (local frente a R2/S3) y el tratamiento de imágenes. Es la misma decisión pendiente que la foto del médico; se toma junto con la landing.

## 7.5 Cambios en pantallas existentes

- **Médico:** una pestaña o relation manager **Seguros**. En **Ubicaciones**, una columna "Establecimiento".
- **Ubicaciones:** una columna y un filtro de establecimiento. Si la ubicación pertenece a un establecimiento, se ve con enlace y la edición se gestiona desde el establecimiento.
- **Panel:** sin métricas nuevas por ahora (`design-system.md`).

---

# 8. Plan de implementación

Cada etapa se puede entregar y probar por separado. Las etapas 1 y 3 incluyen migraciones, así que se ejecutan en plan mode (`CLAUDE.md`).

| Etapa | Contenido | Tests (`tests/Domain/`) |
|---|---|---|
| **1. Esquema** | Migraciones 027–034 (`DATABASE.md` §15), enums PHP sincronizados con los `CHECK`, modelos con `HasUppercaseUlids`, y los permisos en `PermissionSeeder` | `SchemaConstraintsTest`: FK compuesta de país en las sedes, índices parciales de principal, slug único por país. `EnumsMatchChecksTest` con los enums nuevos |
| **2. Establecimientos: dominio** | `CreateFacilityAction`, `UpdateFacilityAction`, `FacilityLocationsAction`, `FacilityContactsAction`, `PublishFacilityAction`, `UnpublishFacilityAction` y la extensión de `SetCatalogStatusAction` a redes | La puerta de publicación; la sede de otro país es rechazada; no se reasigna una sede en silencio; slug con redirección |
| **3. Establecimientos: backoffice** | Recursos Redes y Establecimientos, pestañas Sedes, Contactos y Médicos, "Asociar médico", y columna y filtro en Ubicaciones | `tests/Feature/Backoffice/` con `set()` y `mountAction()` |
| **4. Seguros: dominio** | `DoctorInsurersAction`, `FacilityInsurersAction` y la extensión de `SetCatalogStatusAction` | País distinto rechazado; aseguradora inactiva rechazada; auditoría `doctor.updated` y purga |
| **5. Seguros: backoffice** | Recurso Aseguradoras, pestaña Seguros en Médico y en Establecimiento, y la acción en lote "Asignar seguro" | Pantallas y acción en lote |
| **6. Purga y cierre** | Purga de fichas de médicos al renombrar o desactivar establecimientos y aseguradoras. Actualización de `docs/` si algo cambió durante la implementación | Purga calculada para los médicos vinculados |

**Orden:** primero establecimientos y después seguros, porque los seguros del establecimiento dependen de que el establecimiento exista. Las etapas 4 y 5 pueden adelantarse si el negocio prioriza seguros: la parte de médicos no depende de establecimientos.

---

# 9. Fases posteriores y cómo engancharán

| Funcionalidad | Fase | Punto de enganche ya previsto |
|---|---|---|
| Landing pública del establecimiento | Frontend | `slug`, `description`, `logo_path`, `status` y los contactos ya existen. Ruta `/{pais}/clinicas/{slug}` (§10.2). Habrá que añadir sus rutas a la purga de CDN |
| Filtro y páginas por aseguradora | Frontend | `insurers.slug` y los índices de `doctor_insurers` / `facility_insurers` |
| Importación desde Excel | Importación | Columnas nuevas en la plantilla (establecimiento, aseguradoras). Hará falta `insurer_aliases` y `facility` aliases, al estilo de `city_aliases`, y `name_normalized` en `facilities` para detectar duplicados. Con la importación llega también la **fusión de establecimientos**, que hoy no existe porque la carga es solo manual |
| Seguros por sede | Cuando haya demanda | Columna nullable `doctor_insurers.location_id` |
| Planes de seguro | Tras el claim | Tabla `insurer_plans`, con `doctor_insurers` apuntando opcionalmente a un plan |
| El médico edita sus seguros | Claim | Ampliar el alcance de `profile.update` (§10.3) |
| Cuentas de clínica y gestión de plantilla | V2 | Actor "gestor de clínica" (`MODELO-IDENTIDAD.md`) |

---

# 10. Decisiones cerradas

Eran puntos abiertos. Se aprobaron en octubre de 2026 con la recomendación propuesta.

| # | Cuestión | Decisión | Consecuencia |
|---|---|---|---|
| 1 | ¿Incluir `facility_insurers` (los convenios del establecimiento)? | **Sí** | Tabla 033 y pestaña Seguros en el establecimiento |
| 2 | URL de la landing | **`/{pais}/clinicas/{slug}` para todos los tipos** | Cambiar el tipo no cambia la URL. `clinicas` ya es un slug reservado |
| 3 | Tras el claim, ¿el médico puede editar sus seguros? | **Sí**, con registro en `activity_log` | Amplía el alcance de `profile.update` (`DATABASE.md` §10.2) |
| 4 | ¿Quién carga las aseguradoras de cada país? | **El admin, a mano**, sin seed | Antes de usar "Asignar seguro" en un país, hay que dar de alta sus aseguradoras |

---

# 11. Riesgos

| Riesgo | Mitigación |
|---|---|
| **Datos de seguros desactualizados.** Es el problema operativo número uno de Zocdoc | Registrar en `activity_log` quién vinculó y cuándo. Cuando exista el claim, el médico mantiene sus propios seguros |
| **Establecimientos duplicados** ("Hospital México" y "Hosp. México") sin una acción de fusión | Slug único por país y búsqueda antes de crear. La fusión llega con la importación (§9) |
| **Relación derivada:** un médico sin sede en el hospital no figura como "del hospital" | Aceptado (§5.1) |
| **Purga en cascada:** renombrar un hospital con 300 médicos purga 300 fichas | Se ejecuta en cola, con el job `PurgeCdnPaths` que ya existe. Cloudflare admite purga por URL en lotes |
