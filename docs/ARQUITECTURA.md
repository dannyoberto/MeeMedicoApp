# Arquitectura — Fase 1

**Producto:** MeeMedico.com
**Versión:** 1.0
**Fecha:** septiembre 2026
**Estado:** **DEFINITIVA.** Aprobada el 28 de septiembre de 2026. Los puntos del §12 siguen abiertos, pero ninguno afecta al stack.
**Documento hermano:** `database.md — Fase 1`

---

# 1. Para qué sirve este documento

Deja por escrito **qué stack vamos a usar y por qué**, para que la decisión no se vuelva a discutir cada vez que entre alguien nuevo al proyecto, y para que cuando haya que revisarla se revise con argumentos y no por preferencia.

Incluye deliberadamente las opciones descartadas y el motivo. Una decisión de arquitectura sin sus alternativas documentadas es una opinión.

---

# 2. Resumen ejecutivo

**La decisión en una frase:** usamos Laravel porque el trabajo pesado de MeeMedico no es la interfaz pública, es el backoffice y el pipeline de datos, y ahí Node y Next.js no aportan nada mientras Laravel aporta Filament, Horizon y Artisan.

| Capa | Elección |
|---|---|
| Lenguaje y framework | PHP 8.4 + Laravel 13 |
| Base de datos | PostgreSQL 17 (`pg_trgm`, `unaccent`; PostGIS en Fase 3) |
| Frontend público (SEO) | Blade + islas de React + Tailwind, sin sesión |
| Frontend autenticado | React dentro de rutas autenticadas |
| Backoffice | Filament 5 |
| App móvil (V2) | React Native con Expo, sobre la misma API |
| Búsqueda | PostgreSQL FTS vía Scout → Meilisearch en Fase 2 |
| Colas y caché | Redis + Horizon |
| Permisos | `spatie/laravel-permission` *(pendiente de confirmar, §12)* |
| Auditoría | `spatie/laravel-activitylog` |
| Archivos | Cloudflare R2 o S3 |
| Infraestructura | Laravel Forge + VPS en Miami + PostgreSQL gestionado + Cloudflare |
| Tests | Pest, con foco en Actions de dominio y pipeline de import |
| Errores | Sentry |
| Repositorio | **Uno solo.** Monolito modular, un único despliegue (§9.1) |
| Dominios | `meemedico.com` público y `admin.meemedico.com` backoffice, servidos por la misma app |

> Verificar al arrancar que Filament y los paquetes de Spatie tengan release estable para Laravel 13. Un paquete rezagado bloquea todo el `composer.json`.

---

# 3. El principio que gobierna todo lo demás

MeeMedico no es una aplicación con problema de cómputo. Es un **producto de lectura masiva anónima**.

Si el SEO funciona, el 95% del tráfico serán visitantes sin sesión viendo páginas que cambian pocas veces al mes: la ficha de un médico, el listado de cardiólogos en San José. Ese contenido es prácticamente estático.

**Consecuencia:** la escalabilidad de este producto se resuelve con caché, no con potencia de servidor. Un servidor modesto con las páginas públicas cacheadas en CDN aguanta las 100.000 visitas mensuales del criterio de éxito del MVP sin despeinarse.

Lo que rompe un directorio no es el tráfico. Es haber construido páginas públicas que no se pueden cachear porque dependen de sesión.

**Regla derivada, y es la más importante de este documento:** cualquier decisión que impida cachear las páginas públicas en el borde es una mala decisión, por muy moderna que suene.

---

# 4. Decisión 1 — Backend: Laravel

## 4.1 Las opciones

| | Laravel + islas React | Node/NestJS + React | Next.js full-stack |
|---|---|---|---|
| Backoffice (CRUD médicos, cola de duplicados, claims, moderación) | **Filament, casi gratis** | Lo construimos | Lo construimos |
| Pipeline de import (5 comandos, colas, reintentos) | Artisan + Horizon, nativo | BullMQ + worker propio | Sin equivalente real |
| Migraciones con rollback | Maduras | Prisma/Drizzle, correctas | Prisma/Drizzle |
| Roles, permisos, auditoría | Spatie, probado en producción | Lo escribimos | Lo escribimos |
| Suscripciones (Fase 3) | Cashier | Lo escribimos | Lo escribimos |
| SEO de páginas públicas | Blade + CDN | Depende del front | **ISR, excelente** |
| Runtimes en producción | 1 | 2 | 1 |

## 4.2 Los tres argumentos que deciden

**1. La Fase 1 es más operación que sitio público.**
El sitio público son cinco plantillas: ficha, listado, home, búsqueda, especialidad. Eso lo hace cualquier stack. El backoffice con cola de revisión de duplicados, aprobación de claims, gestión de lotes de importación y moderación es varias veces más trabajo, y Filament lo da hecho.

Hay una asimetría que conviene ver: **con Laravel nos falta lo fácil (HTML público). Con Next nos falta lo difícil (operación, colas, admin).**

**2. El import necesita un runtime de trabajos por lotes, no un framework web.**
Cinco comandos reejecutables, sobre colas, con reintentos y visibilidad, procesando decenas de miles de filas con revisión humana intercalada. Eso es Artisan + Horizon. En Node se monta a mano; en Next el concepto no existe.

**3. La escalabilidad es un problema de caché.**
Blade sirviendo HTML plano, cacheado entero en Cloudflare, resuelve el §3 con una pieza menos de infraestructura. El ISR de Next.js es más elegante como mecanismo, pero el resultado que ve el usuario y que mide Google es el mismo.

## 4.3 El mejor argumento a favor de Next.js, y por qué no basta

Next.js resolvería de forma nativa cosas que con Laravel hay que ensamblar: ISR para las páginas SEO, un solo lenguaje de punta a punta con tipos compartidos con React Native, Server Components como modelo de islas integrado, y despliegue en el borde sin administrar servidores.

Si MeeMedico fuera **solo** el sitio público, Next.js sería al menos tan buena elección como Laravel, y probablemente mejor en distribución global.

No lo es. Un directorio poblado por carga masiva, con deduplicación manual, verificación profesional y claims a moderar es un producto **intensivo en operación administrativa**. Ahí Laravel gana por márgenes grandes.

## 4.4 Por qué descartamos el híbrido Laravel API + Next.js front

Es la variante que suena más razonable y es la peor de las tres.

**Lo que se paga:** dos despliegues y dos runtimes; autenticación duplicada con CORS y cookies cross-origin; validación duplicada entre PHP y TypeScript que diverge en tres meses; un salto de red extra en cada render (Next llama a Laravel, Laravel llama a la base de datos); y **seguimos necesitando Filament**, que corre en Laravel, así que acabamos con Blade y Next conviviendo igualmente.

**Lo que se gana:** React en las páginas públicas. Que ya lo tenemos con islas, sin ninguno de esos costes.

Para un equipo grande con front y back separados, la división tiene sentido organizativo. Para un equipo pequeño, es duplicar la superficie operativa para obtener algo que ya se podía tener.

## 4.5 Dónde Laravel sería mala elección

Por honestidad: procesos de larga duración, websockets a gran escala, o cómputo intensivo. No es nuestro caso. Cuando llegue telemedicina en V2, el vídeo lo pone un proveedor externo (Twilio, Daily, Agora), no nuestro backend.

---

# 5. Decisión 2 — Base de datos: PostgreSQL 17

Cambiamos respecto al borrador inicial, que asumía MySQL 8. La justificación completa está en `database.md §2`; el resumen:

| Problema real de MeeMedico | MySQL 8 | PostgreSQL |
|---|---|---|
| "Máximo una especialidad/ubicación/contacto primaria" | Columna generada + índice único (truco) | `CREATE UNIQUE INDEX ... WHERE is_primary`, nativo |
| **Deduplicación por nombre en la carga masiva** | Sin matching difuso indexable | `pg_trgm` + `similarity()` con índice GIN |
| Búsqueda "cardiólogo en San José" | FULLTEXT limitado, sin control de acentos | `tsvector` + `unaccent` + diccionario español |
| Mapas y "cerca de mí" (Fase 3) | Spatial básico | PostGIS |
| Migración que falla a mitad | Deja la base a medias | **DDL transaccional: revierte entera** |

El argumento decisivo es el segundo. El corazón del pipeline de importación es detectar candidatos a duplicado por nombre normalizado. En PostgreSQL es una consulta indexada; en MySQL es código PHP sobre bloques de nombres, más lento y más frágil.

**Ventana cerrada:** esta decisión se congela con la primera migración. Ya está tomada.

---

# 6. Decisión 3 — Frontend: Blade con islas de React

## 6.1 Las opciones

| Opción | Veredicto |
|---|---|
| **Blade + islas de React + Filament** | **Elegida** |
| Blade + Livewire + Alpine | Válida, pero con equipo que sabe React no tiene sentido cargar con dos paradigmas de cliente más React Native en V2 |
| Inertia + React con SSR | Descartada, §6.3 |
| Next.js headless | Descartada, §4.4 |

## 6.2 La regla: tres superficies, no una

| Superficie | Tecnología | ¿Cacheable en borde? |
|---|---|---|
| Ficha de médico `/{pais}/medicos/{slug}` | Blade puro, cero JS | **Sí, íntegra** |
| Listado especialidad + ciudad | Blade renderiza la lista indexable; isla React para mapa y filtros | **Sí** el HTML; el mapa pide JSON aparte |
| Home y búsqueda | Blade + isla React de autocompletado | **Sí** |
| Flujo de claim | React sobre Blade, con Sanctum | No, ni falta |
| Dashboard del médico | React, aplicación pequeña en ruta autenticada | No |
| Backoffice | Filament | No |

**El punto fino del listado**, donde se juegan a la vez el SEO y la interactividad: los resultados se renderizan en el HTML inicial desde el servidor. El mapa, el "buscar en esta área" y los filtros son una isla de React que consume un endpoint JSON y actualiza la vista en cliente. Google indexa el HTML servido; el usuario obtiene la experiencia tipo Zocdoc. No hay que elegir entre las dos cosas.

## 6.3 Por qué no Inertia, que es la tentación natural

Inertia resuelve un problema real, pero no el nuestro. Obliga a mantener un proceso Node de SSR junto al de PHP, con su propio modo de fallo, y convierte **todas** las páginas en páginas React, incluidas las decenas de miles de fichas que no necesitan interactividad. Se paga hidratación y bundle en el 95% del tráfico para beneficiar al 5%.

Inertia brilla cuando la aplicación entera es una app con sesión: un CRM, un panel, un SaaS. MeeMedico es un sitio de contenido con una app pequeña encima. Elegir Inertia sería optimizar la parte pequeña a costa de la grande.

## 6.4 La trampa que hay que evitar de forma explícita

**Ninguna página pública puede ser un componente Livewire.** Livewire requiere sesión y token CSRF, así que una página Livewire **no se puede cachear en el borde**. Construir la ficha del médico como componente Livewire porque es cómodo convierte la página más visitada y más cacheable del sistema en una que golpea PHP y la base de datos en cada visita.

Filament usa Livewire por dentro, pero está confinado al backoffice y ahí no importa.

## 6.5 Stack de cliente

- **TypeScript obligatorio.** Sin excepciones.
- Vite (incluido en Laravel), Tailwind compartido entre Blade y React.
- MapLibre o Leaflet para el mapa. Google Maps solo si el coste se justifica.
- TanStack Query para el estado servidor de las islas y del dashboard.
- **Node no va a producción.** Un runtime que operar, no dos. Node se queda en el build.

## 6.6 Presupuesto de JavaScript

**Menos de 100 KB comprimidos en páginas públicas**, medido en CI, y el build falla si se supera. Sin esta regla, las islas se expanden solas hasta comerse la página y perdemos el motivo entero de haber elegido Blade.

---

# 7. Decisión 4 — Búsqueda

El producto es *search-first*, pero eso no significa comprar un motor de búsqueda el primer día.

- **Fase 1 (hasta ~50.000 médicos):** PostgreSQL con `tsvector`, `unaccent` y `pg_trgm`. Coste cero, una dependencia menos.
- **Fase 2, con facetas complejas y autocompletado instantáneo:** Meilisearch o Typesense autoalojados.
- **Algolia:** no antes de tener ingresos. Excelente y caro, y su precio escala con exactamente lo que queremos que crezca.

**La decisión que sí se toma hoy** es no acoplar el código a la implementación: toda la búsqueda detrás de una interfaz `DoctorSearchService`, o directamente sobre Laravel Scout. Cambiar de motor en Fase 2 debe ser cambiar configuración, no reescribir controladores.

---

# 8. Decisión 5 — Infraestructura

| Componente | Producción | Desarrollo (Windows) | Motivo |
|---|---|---|---|
| Servidor | VPS gestionado con **Laravel Forge**, o Laravel Cloud | `php artisan serve` | 4 vCPU cubren toda la Fase 1 |
| Región | **Miami** | — | Hub de latencia natural para CR, GT y RD. São Paulo está peor conectado con Centroamérica de lo que sugiere el mapa |
| Base de datos | PostgreSQL 17 gestionado, con backups y PITR | PostgreSQL 17 local | No administramos nosotros la base de datos de un directorio médico. Las extensiones `pg_trgm` y `unaccent` deben estar disponibles: verificar con el proveedor antes de contratar |
| CDN y caché de borde | **Cloudflare**, caché de página completa para tráfico anónimo | — | Es la palanca principal de escalabilidad |
| Caché y sesiones | Redis | Driver `database` | Redis no tiene build oficial mantenido en Windows. El cambio es de configuración, no de código |
| Colas | Sistema de colas de Laravel sobre Redis | Driver `database`, con `queue:work` | Igual que arriba. El pipeline de importación depende de las colas, no del backend concreto |
| Monitorización de colas | **Horizon** | No disponible | Horizon requiere la extensión `pcntl`, que solo existe en Unix. Se instala únicamente en el servidor, sin tocar código de aplicación |
| Archivos | Cloudflare R2 o S3 | Disco local | R2 no cobra egreso, relevante para servir fotos de médicos |
| Errores | Sentry | Sentry, entorno aparte | Desde el día uno |
| CI/CD | GitHub Actions con Pest, despliegue automático a staging | — | **El CI corre sobre Linux y es el árbitro.** Necesitamos staging con datos reales importados |


## 8.1 Invalidación de caché: diseñarla desde el principio

Cuando un médico se publica, se actualiza o se fusiona, hay que purgar su URL y los listados que lo contienen. Se resuelve con eventos de modelo que despachan un job de purga.

Si no se diseña desde el principio, se acaba poniendo TTL de cinco minutos a todo y se pierde el beneficio entero.

**Implementación.** Las Actions no emiten eventos de modelo. Usan `DoctorCachePurge`, que calcula las rutas públicas del médico **antes y después** del cambio (su ficha y cada listado `/{pais}/{especialidad}/{ciudad}` de su ubicación principal) y despacha el job `PurgeCdnPaths` en cola y **después del commit**. Detrás está la interfaz `CdnPurger`: hoy el driver `log` (`CDN_DRIVER=log`) solo registra las rutas. Cloudflare será otro driver, sin tocar las Actions. Cloudflare purga por URL en todos sus planes; la purga por prefijo o etiqueta es de Enterprise, de ahí que se calculen URLs concretas.

## 8.2 Imágenes

Las fotos de los médicos son lo único que puede arruinar el LCP. Almacenamiento en R2/S3, conversión a WebP/AVIF, varios tamaños, `loading="lazy"` salvo la principal. **En el pipeline de importación, no después.**

---

# 9. Organización del código

## 9.1 Un solo proyecto, un solo despliegue

**No son dos aplicaciones.** Backoffice, sitio público y área autenticada viven en el mismo repositorio, el mismo `composer.json` y el mismo despliegue.

Filament es un **panel montado dentro de la aplicación Laravel**, no un proyecto aparte: comparte modelos, Actions, Policies y base de datos. Separarlo obligaría a duplicar el dominio entero o a inventar una API interna entre dos aplicaciones nuestras, que es exactamente el coste del híbrido descartado en el §4.4.

Lo que sí se separa es el **dominio web**, no el proyecto:

| Hostname | Sirve | Caché |
|---|---|---|
| `meemedico.com` | Sitio público y área autenticada del médico | Página completa en Cloudflare para tráfico anónimo |
| `admin.meemedico.com` | Panel de Filament | Sin caché, WAF y rate limiting propios |

Se configura con `->domain()` en el panel de Filament. Dos ventajas concretas sobre montarlo en `/admin`:

1. Las reglas de caché y seguridad de Cloudflare se aplican **por hostname**, mucho menos frágil que por path.
2. Evita que `/admin` colisione con el prefijo de país de las URLs `/{pais}/`. (La lista de slugs reservados sigue siendo necesaria de todos modos: `medicos`, `especialidades`, `api`, `assets`.)

**Procesos en producción**, todos desde el mismo código:

```
php-fpm                       peticiones web
php artisan horizon           colas: import, purga de CDN, notificaciones
php artisan schedule:run      cron
```

## 9.2 Estructura de carpetas

```
meemedico/
├── app/
│   ├── Models/                    TODOS los modelos Eloquent, planos: Doctor, User,
│   │   │                          Country, ImportBatch... (convención de Laravel)
│   │   └── Concerns/              HasUppercaseUlids (ver DATABASE.md §3.2)
│   ├── Domain/                    ← el núcleo: aquí viven las reglas
│   │   ├── Directory/
│   │   │   ├── Actions/           CreateDoctor, PublishDoctor,
│   │   │   │                      MergeDoctors, UpdateSlug
│   │   │   ├── Enums/             DoctorStatus, VerificationStatus, ContactType
│   │   │   └── Data/              DTOs
│   │   ├── Identity/              Enums (UserStatus)
│   │   ├── Claim/                 ApproveClaim, RejectClaim, Enums
│   │   ├── Import/
│   │   │   ├── Commands/          los 5 comandos de Artisan
│   │   │   ├── Jobs/              chunks sobre cola
│   │   │   ├── Normalizers/       nombres, teléfonos, ciudades, especialidades
│   │   │   ├── Matchers/          la cascada de deduplicación
│   │   │   ├── Actions/           ApplyImportBatch
│   │   │   └── Enums/
│   │   ├── Geo/                   Enums
│   │   └── Search/                DoctorSearchService (interfaz + driver)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Public/            Blade, sin estado, cacheable
│   │   │   ├── Doctor/            área autenticada
│   │   │   └── Api/V1/            JSON: islas hoy, app móvil en V2
│   │   ├── Middleware/            ResolveCountry
│   │   ├── Requests/
│   │   └── Resources/             API Resources (origen de los tipos TS)
│   ├── Filament/
│   │   ├── Resources/             DoctorResource, ClaimResource,
│   │   │                          ImportBatchResource, ...
│   │   ├── Pages/                 cola de revisión de duplicados
│   │   └── Widgets/               KPIs
│   ├── Policies/
│   ├── Providers/
│   └── Support/
├── resources/
│   ├── views/
│   │   ├── public/                doctor.blade.php, listing, home, specialty
│   │   ├── doctor/                shell del área autenticada
│   │   ├── components/            componentes Blade compartidos
│   │   └── layouts/
│   ├── js/
│   │   ├── islands/               SearchMap.tsx, Autocomplete.tsx,
│   │   │                          FilterPanel.tsx, ContactButtons.tsx
│   │   ├── dashboard/             app React del médico
│   │   ├── claim/                 flujo de reclamación
│   │   ├── lib/                   cliente de API, tipos generados
│   │   └── bootstrap-islands.ts   monta las islas presentes en el DOM
│   └── css/
├── routes/
│   ├── web.php                    públicas, con prefijo de país
│   ├── doctor.php                 autenticadas
│   ├── api.php                    /api/v1
│   └── console.php
├── database/
│   ├── migrations/                ver database.md §15
│   ├── seeders/                   PermissionSeeder ← lista canónica
│   └── factories/
├── tests/
│   ├── Domain/                    Actions e invariantes: el foco
│   ├── Feature/
│   └── Unit/
└── docs/
    ├── arquitectura.md
    ├── database.md
    ├── modelo-dominio.md
    └── modelo-identidad.md
```

**Por qué `app/Domain` y no la estructura por defecto de Laravel:** la carpeta separa lo que cambia por razones de producto (las reglas) de lo que cambia por razones técnicas (controladores, vistas, panel). Es lo que permite que el importador, Filament, el sitio web y la futura API móvil compartan exactamente las mismas reglas en lugar de reimplementarlas cuatro veces.

**Cómo se montan las islas:** la vista Blade emite un contenedor con los props serializados, y `bootstrap-islands.ts` busca esos contenedores al cargar y monta el componente correspondiente.

```blade
<div data-island="SearchMap" data-props='@json($mapProps)'></div>
```

Así el HTML indexable lo sigue generando el servidor y React solo se hace cargo del widget.

## 9.3 Tres reglas que valen más que la estructura de carpetas

**1. Las invariantes que la base de datos no puede imponer viven en Actions de dominio, nunca en controladores.**

`PublishDoctorAction`, `ApproveClaimAction`, `MergeDoctorsAction`, `ApplyImportBatchAction`. Si la regla "no publicar sin especialidad, ubicación y contacto público" está en un controlador, el comando de consola del importador la saltará. La lista completa está en `database.md §11`.

**Regla de oro del repositorio:** si una de esas reglas aparece dentro de un controlador o de un recurso de Filament, es un bug.

**2. No usamos el patrón Repository sobre Eloquent.** En Laravel solo añade ceremonia; Eloquent ya es la capa de acceso a datos.

**3. Los endpoints JSON que alimentan las islas son el primer borrador de la API móvil.**

Diseñados como API Resources, versionados bajo `/api/v1/`, con Sanctum, y llamando a las mismas Actions que usan los controladores web. En V2, la app de Expo consume lo que ya existe y solo se añade lo que falte.

Esta es la única razón por la que insistimos en la regla 1: cada regla escrita en un controlador web es una regla que habrá que reescribir o duplicar cuando llegue el móvil.

---

# 10. Multipaís

**Una sola aplicación, una sola base de datos.** El país es una dimensión de los datos (`country_id`), no un límite de infraestructura.

Nada de base de datos por país, ni despliegue por país, ni multi-tenancy. Separar obligaría a multiplicar migraciones, imposibilitaría las métricas agregadas y complicaría el caso del médico con consulta en dos países.

**Implementación:** middleware que resuelve el país desde el segmento `/{pais}/` de la URL y lo mete en el contexto de la petición.

**Lanzar Guatemala** es insertar filas en `countries`, `regions` y `cities` y activar el país. No se despliega nada.

**URLs canónicas:**
```
/{pais}/medicos/{slug}
/{pais}/{especialidad}/{ciudad}
```
Con el nombre completo del país (`costa-rica`), no el código ISO. La palabra clave en la URL tiene valor SEO real.

---

# 11. Lo que no vamos a hacer, y por qué se menciona

Decisiones que suenan bien en una conversación de arquitectura y que hundirían el proyecto en esta etapa:

| No haremos | Motivo |
|---|---|
| Microservicios | No hay problema de escala ni equipos independientes. Solo coste |
| Headless con Next.js | §4.4 |
| GraphQL | Resuelve un problema de múltiples clientes heterogéneos que no tenemos |
| Kubernetes | Un VPS con Forge hasta bien pasado el PMF |
| MongoDB u otro NoSQL | El dominio es relacional en estado puro |
| Backoffice a medida | Filament |
| Event sourcing / CQRS | `activity_log` cubre la necesidad de auditoría con el 2% del coste |
| Telemedicina propia en V2 | Proveedor externo |
| Algolia desde el día uno | §7 |
| Livewire en páginas públicas | §6.4 |

---

# 12. Puntos abiertos

| # | Decisión | Estado |
|---|---|---|
| 1 | `spatie/laravel-permission` en lugar de tablas `user_roles` / `role_permissions` propias | **Pendiente.** Contradice una decisión marcada como aprobada en el Modelo de Identidad v1.0. A favor: caché de permisos integrada, integración con Filament vía `filament-shield`, años de producción. En contra: los nombres de tabla los fija el paquete |
| 2 | Página propia para subespecialidades, o solo filtro dentro de la especialidad padre | **Pendiente.** Decisión de SEO, hay que tomarla antes de generar el sitemap |
| 3 | Fuente del archivo de carga masiva y su perfilado | **Pendiente.** Ver `database.md §12.5`. Puede obligar a partir la importación en dos fases |
| 4 | Google Maps frente a MapLibre/MapTiler para el mapa de Fase 3 | Pendiente, sin urgencia |

---

# 13. Definición de calidad, en números

"Producto de calidad" se disuelve si no se define. Estos son los objetivos que determinan si el producto crece:

- **LCP por debajo de 2,5 s en móvil** en fichas y listados.
- **JavaScript en páginas públicas por debajo de 100 KB comprimidos**, verificado en CI.
- **Ratio de acierto de caché superior al 90%** en tráfico anónimo.
- **Cero fichas publicadas** sin especialidad, ubicación y contacto público.
- **Tasa de duplicados por debajo del 2%** tras la carga masiva, medida y reportada por lote.
- **Cobertura de tests concentrada en las Actions de dominio y el pipeline de import**, no en los controladores.
- **Sentry y `activity_log` activos desde el día uno.**

---

# 14. Cuándo revisar esta decisión

Un criterio, no una opinión: **la regla se invierte cuando la interfaz autenticada supere en complejidad al sitio público.**

Hoy el área autenticada son cuatro pantallas. En Fase 3, con agenda, disponibilidad, gestión de citas, Smart Check In y sincronización con Google Calendar, será una aplicación de verdad.

Si en ese momento el React dentro de Laravel se siente apretado, **la migración natural no es a Next.js**: es mover únicamente el área autenticada a Inertia con React, dejando las páginas públicas en Blade y el backoffice en Filament. Cambio incremental, sin tocar el dominio, y decidido con información que hoy no tenemos.

Eso es lo que hace buena a esta arquitectura: no obliga a acertar hoy una decisión de dentro de dos años.

---

# 15. Riesgos reconocidos

Por honestidad, lo que esta arquitectura no resuelve:

1. **Dos lenguajes.** PHP en servidor y TypeScript en cliente significa que los contratos de la API no están tipados de punta a punta. Se mitiga generando tipos de TypeScript desde los API Resources, no escribiéndolos a mano.
2. **Filament ata el backoffice a Livewire.** Si algún día hubiera que salir de Filament, el backoffice se reescribe. Es un riesgo aceptado a cambio de semanas de trabajo ahorradas hoy.
3. **Blade e islas de React exigen disciplina.** Nada impide técnicamente convertir la ficha del médico en una página React. Solo lo impide la revisión de código y el presupuesto de JS del §6.6.
4. **Un solo servidor de aplicación** es un punto único de fallo hasta que se justifique el balanceo. Se mitiga con la caché de Cloudflare, que sigue sirviendo páginas públicas aunque el origen caiga.
5. **El entorno de desarrollo no coincide con producción.** Se desarrolla en
   Windows y se despliega en Linux. Diferencias conocidas: Horizon y `pcntl` no
   disponibles en local, Redis sustituido por el driver `database`, y el sistema
   de archivos de Windows es insensible a mayúsculas, así que una ruta mal
   escrita funciona en local y falla en el servidor. Mitigación: el CI corre
   sobre Linux y es el que decide si algo está bien.

---

# 16. Lo que de verdad decide si el producto es bueno

Conviene decirlo aunque sea incómodo: **la elección de stack es casi irrelevante para la calidad final de MeeMedico.** Con cualquiera de las opciones evaluadas, bien ejecutada, saldría un producto aceptable.

Lo que va a decidir si esto funciona es:

1. **La calidad de los datos de la carga masiva.** Un directorio con nombres mal escritos, ciudades mal mapeadas, teléfonos muertos y médicos duplicados es un mal producto por muy bien construido que esté el código. Es donde se va a gastar más esfuerzo del que hoy se estima.
2. **La disciplina operativa del claim y la moderación.** Si aprobar un claim tarda una semana, los médicos abandonan y el motor de adquisición no arranca.
3. **Que las páginas SEO no sean contenido pobre.** Miles de fichas con solo nombre y especialidad no posicionan; posicionan peor que no tenerlas.

La arquitectura elegida está orientada a que el equipo dedique su tiempo a esos tres problemas, en lugar de a mantener dos despliegues y un proceso de SSR.

---

# 17. Documentos relacionados

| Documento | Contenido |
|---|---|
| `database.md — Fase 1` | Esquema completo: 33 tablas, índices, restricciones, orden de migraciones, pipeline de importación |
| `MODULO-ESTABLECIMIENTOS-SEGUROS.md` | Establecimientos y aseguradoras en el backoffice (alcance ampliado en octubre de 2026). No cambia el stack |
| Modelo de Dominio — Directorio Médico Básico | Conceptos del dominio médico *(pendiente de actualizar a v2.0)* |
| Modelo de Identidad y Administración | Actores, roles, reglas de autorización *(pendiente de actualizar a v2.0)* |
| `PermissionSeeder.php` | **Lista canónica de permisos.** Los documentos la referencian, no la copian |
