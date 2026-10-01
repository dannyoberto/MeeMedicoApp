# Database Specification — Fase 1

**Producto:** MeeMedico.com
**Fase:** 1 — Directorio Médico Básico + Identity & Administration
**Versión:** 2.0 (reemplaza la v1.0 sobre MySQL)
**Motor:** PostgreSQL 17
**Framework:** Laravel 13 / PHP 8.4
**Identificadores:** ULID
**Estado:** Especificación técnica lista para migraciones

---

# 1. Alcance de este documento

Consolida en una sola especificación de persistencia:

1. Modelo de Dominio — Directorio Médico Básico
2. Modelo de Identidad y Administración — Fase 1
3. Las decisiones tomadas durante la revisión técnica: multipaís, carga masiva con cobertura parcial de colegiado, claim en Fase 1, y ficha pública con contacto visible.

La base de datos debe soportar, en esta fase:

- Crear médicos sin necesidad de que exista un usuario.
- Cargar el directorio inicial mediante importación masiva idempotente y auditable.
- Resolver duplicados con intervención humana, sin fusiones automáticas.
- Publicar páginas SEO por país, especialidad y ciudad.
- Mostrar datos de contacto públicos y medir las llamadas.
- Permitir que un médico reclame su perfil y que un admin lo apruebe.
- Diferenciar creación, administración, verificación y reclamación.
- Roles y permisos desacoplados.
- Dejar preparada la evolución hacia búsqueda avanzada, membresías, reservas, reviews y clínicas sin tocar el núcleo.

---

# 2. Cambios respecto a la v1.0 y por qué

Esta sección existe para que el equipo entienda qué se modificó de la propuesta original y no lo revierta por inercia.

| # | Cambio | Motivo |
|---|---|---|
| 1 | **MySQL 8 → PostgreSQL 17** | Índices únicos parciales, `pg_trgm` para deduplicación difusa en la carga masiva, búsqueda en español con acentos, PostGIS en Fase 3, DDL transaccional |
| 2 | **`doctors.country_id` NOT NULL** | El país no puede derivarse de una ubicación que puede no existir. Habilita unicidad de slug y de licencia por país, y el scoping de URLs |
| 3 | **Slug único por país**, no global | URLs `/{pais}/medicos/{slug}`; evita colisiones entre países |
| 4 | **`cities.country_id` denormalizado + slug único por país** | El slug de ciudad viaja en la URL dentro del ámbito país; la unicidad por región no lo garantiza |
| 5 | **FK compuestas en la jerarquía geográfica** | La consistencia ciudad→región→país la garantiza el motor, no la aplicación |
| 6 | **`ENUM` de base de datos → `varchar` + `CHECK`** | Los estados van a crecer (`status` ya creció con `merged`); alterar un tipo enum es caro y frágil. La verdad vive en un `enum` de PHP |
| 7 | **`doctor_claims` como entidad** | `claim_status = 'pending'` no podía representar quién solicitó. Sin esta tabla el flujo de claim es inoperable |
| 8 | **`doctor_contacts` como entidad** | En Fase 1 el paciente ve la ficha y llama. Sin contacto, la ficha no resuelve nada. El flag `is_public` es el punto de anclaje de la futura capa de membresía |
| 9 | **`doctor_languages` en lugar de `languages JSON`** | El idioma es un filtro declarado del MVP; un JSON dentro de una tabla 1:1 es el peor sitio para un atributo filtrable |
| 10 | **`doctor_external_references` 1:N** | Al fusionar duplicados, el superviviente debe conservar las referencias externas de ambos. Una columna no puede |
| 11 | **`import_batches` + `import_rows`** | Con cobertura parcial de colegiado el import necesita revisión humana, y la revisión necesita estado persistente |
| 12 | **`doctor_suppressions`** | Sin lista de supresión, el médico que pide salir reaparece en el siguiente lote |
| 13 | **`slug_redirects`** | Producto SEO-first: cambiar un slug indexado sin 301 destruye posicionamiento |
| 14 | **Trazabilidad en verificación** (`verified_at`, `verified_by_user_id`, `verification_source`) | Posicionamiento *trust-first*: hay que poder responder quién verificó y cómo |
| 15 | **`doctors.user_id` con `ON DELETE RESTRICT`** | Con `SET NULL` quedaba el estado imposible `claim_status='claimed'` sin usuario |
| 16 | **Índice `(latitude, longitude)` eliminado** | Un B-tree compuesto no sirve para consultas de radio; se sustituye por PostGIS en Fase 3 |
| 17 | **`specialties.parent_id`** | Las subespecialidades condicionan la taxonomía de URLs, que es lo más caro de revertir |
| 18 | **`city_aliases` y `specialty_aliases`** | El mapeo de texto libre a catálogo es el 70% del esfuerzo del import; los alias hacen que cada lote requiera menos intervención que el anterior |
| 19 | **RBAC sobre `spatie/laravel-permission`** | Ver §6. Cambian los nombres de las tablas respecto a la v1.0 |
| 20 | **`doctor_contact_events`** | La métrica "te contactaron N veces" es el argumento de venta de premium en Fase 3 y no se puede reconstruir hacia atrás |

**Total: 27 tablas propias** (la v1.0 preveía 14), más las tablas de infraestructura de Laravel y la de `activity_log`. El crecimiento no viene de anticipar fases futuras: 13 de las 13 tablas nuevas salen de tres decisiones de producto ya tomadas (carga masiva, claim en Fase 1, y ficha con contacto visible).

---

# 3. Convenciones

## 3.1 Extensiones requeridas

```sql
CREATE EXTENSION IF NOT EXISTS pg_trgm;   -- matching difuso de nombres
CREATE EXTENSION IF NOT EXISTS unaccent;  -- búsqueda insensible a acentos
```

PostGIS **no** se instala en Fase 1. Se añadirá en Fase 3 junto con la columna generada `geography(Point, 4326)` sobre `locations`.

## 3.2 Identificadores

Todas las entidades usan ULID, salvo la tabla de eventos de alto volumen (§14.3).

```sql
CREATE DOMAIN ulid AS varchar(26)
    COLLATE "C"
    CHECK (VALUE ~ '^[0-7][0-9A-HJKMNP-TV-Z]{25}$');
```

El dominio hace tres cosas: fija el tipo en un solo sitio, impone la collation `C` (comparación por bytes, la más rápida) y valida el alfabeto Crockford base32 que genera `Str::ulid()`. Si el equipo prefiere no usar dominios por compatibilidad con herramientas externas, sustituir por `varchar(26) COLLATE "C"` en cada columna; el resto de la especificación no cambia.

> En Laravel: `$table->string('id', 26)->primary()` más un `DB::statement` para el dominio, o directamente `DB::statement` en la primera migración. Los modelos usan el trait `App\Models\Concerns\HasUppercaseUlids`, **no** `HasUlids` directamente: `HasUlids` genera los ULID en minúsculas y el `CHECK` del dominio los rechaza. Las columnas creadas con el `Schema` builder usan `$table->rawColumn('col', 'ulid')`.

## 3.3 Fechas

Todas las columnas temporales son **`timestamptz`**, nunca `timestamp`. El producto opera en cuatro husos (CR y GT en UTC-6, RD y VE en UTC-4) y Fase 3 traerá disponibilidad horaria. Laravel almacena en UTC; `timestamptz` garantiza que una comparación entre husos sea correcta desde hoy.

## 3.4 Estados

No se usan tipos `ENUM` de PostgreSQL. Cada estado es `varchar` con `CHECK`, y la lista canónica vive en un `enum` de PHP con cast de Eloquent:

```sql
status varchar(20) NOT NULL DEFAULT 'draft'
    CONSTRAINT doctors_status_check
    CHECK (status IN ('draft','active','inactive','suspended','merged'))
```

Añadir un estado es `ALTER TABLE ... DROP CONSTRAINT` + `ADD CONSTRAINT`, operación de milisegundos que no reescribe la tabla.

## 3.5 Borrado

Ninguna entidad del directorio se borra físicamente. `ON DELETE RESTRICT` es el valor por defecto de todas las FK; `CASCADE` se usa únicamente en las tablas que son parte del agregado de su padre (`doctor_profiles`, `doctor_contacts`, pivotes). Las bajas se representan con `status`.

## 3.6 Nomenclatura

- Tablas en plural, snake_case.
- FK: `{tabla_singular}_id`.
- Índices: `{tabla}_{columnas}_{tipo}` (`idx`, `uniq`, `gin`, `chk`).
- Columnas normalizadas para matching: prefijo `*_normalized`, siempre escritas por la aplicación, nunca por el motor.

---

# 4. Configuración de búsqueda en español

Se crea una configuración de búsqueda propia que combina stemming español con eliminación de acentos. Es lo que permite que `perez` encuentre `Pérez` y que `cardiologos` encuentre `cardiólogo`.

```sql
CREATE TEXT SEARCH CONFIGURATION es_unaccent (COPY = spanish);

ALTER TEXT SEARCH CONFIGURATION es_unaccent
    ALTER MAPPING FOR hword, hword_part, word
    WITH unaccent, spanish_stem;
```

Y una función `unaccent` inmutable, necesaria para poder usarla dentro de índices y columnas generadas (la versión que trae la extensión es `STABLE`, y PostgreSQL rechaza expresiones no inmutables en índices):

```sql
CREATE OR REPLACE FUNCTION immutable_unaccent(text)
RETURNS text AS $$
    SELECT public.unaccent('public.unaccent', $1)
$$ LANGUAGE sql IMMUTABLE PARALLEL SAFE STRICT;
```

> **Orden importa:** `es_unaccent` debe existir antes de crear `doctors`, porque la columna generada `search_vector` la referencia. Ponlo en la primera migración, junto a las extensiones. Lo mismo aplica al restaurar un dump.

---

# 5. Mapa de tablas

| Bloque | Tablas |
|---|---|
| Identidad y acceso | `users`, `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` |
| Geografía | `countries`, `regions`, `cities`, `city_aliases` |
| Catálogos | `specialties`, `specialty_aliases`, `languages` |
| Directorio | `doctors`, `doctor_profiles`, `doctor_specialties`, `doctor_languages`, `locations`, `doctor_locations`, `doctor_contacts` |
| Identidad externa | `doctor_external_references` |
| Claim | `doctor_claims` |
| Importación | `import_batches`, `import_rows` |
| Operación | `doctor_suppressions`, `slug_redirects`, `doctor_contact_events`, `activity_log` |

---

# 6. Identidad y acceso

## 6.1 Decisión: RBAC propio vs. paquete

La v1.0 definía `user_roles` y `role_permissions` propias. Recomiendo usar **`spatie/laravel-permission`** en su lugar.

**A favor del paquete:** caché de permisos integrado (sin él, cada comprobación de autorización son dos joins por petición), integración directa con Filament vía `filament-shield`, que genera los permisos por recurso automáticamente, y años de uso en producción.

**En contra:** los nombres de tabla los fija el paquete (`model_has_roles` en vez de `user_roles`) y usa una relación polimórfica, algo menos limpia que una FK directa a `users`. También pierdes `roles.status` y `roles.slug`; el paquete usa `name` como clave.

**Recomendación:** usar el paquete. Los principios aprobados en el Modelo de Identidad (rol separado de usuario, permiso separado de rol, N:N en ambas relaciones) se cumplen igual. El coste es cosmético; el beneficio es real.

> Publica la migración del paquete y ajusta `model_morph_key` a `varchar(26)` para ULID antes de ejecutarla. El esquema de abajo es el que produce; **verifícalo contra el archivo publicado** en lugar de copiarlo a ciegas, porque puede variar entre versiones mayores.

## 6.2 `users`

```sql
CREATE TABLE users (
    id                ulid PRIMARY KEY,
    name              varchar(150) NOT NULL,
    email             varchar(255) NOT NULL,
    password          varchar(255) NOT NULL,

    status            varchar(20) NOT NULL DEFAULT 'active'
                      CONSTRAINT users_status_chk
                      CHECK (status IN ('active','inactive','suspended')),

    email_verified_at timestamptz NULL,
    last_login_at     timestamptz NULL,
    remember_token    varchar(100) NULL,

    created_at        timestamptz NOT NULL DEFAULT now(),
    updated_at        timestamptz NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX users_email_uniq ON users (lower(email));
CREATE INDEX users_status_idx ON users (status) WHERE status <> 'active';
```

Dos notas:

- La unicidad es sobre `lower(email)`, no sobre `email`. Evita depender de la collation y hace imposible registrar `Juan@X.com` y `juan@x.com` como cuentas distintas. La aplicación debe además **almacenar el email ya en minúsculas**.
- El índice de `status` es **parcial**. Un índice completo sobre una columna donde el 99% de las filas vale `active` no lo usaría el planificador; el parcial es pequeño y sirve exactamente para la consulta que importa (listar cuentas suspendidas o inactivas en el backoffice). Este criterio se repite en toda la especificación.

## 6.3 Tablas del paquete de permisos

```sql
CREATE TABLE permissions (
    id         ulid PRIMARY KEY,
    name       varchar(150) NOT NULL,
    guard_name varchar(50)  NOT NULL DEFAULT 'web',
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT permissions_name_guard_uniq UNIQUE (name, guard_name)
);

CREATE TABLE roles (
    id         ulid PRIMARY KEY,
    name       varchar(100) NOT NULL,
    guard_name varchar(50)  NOT NULL DEFAULT 'web',
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT roles_name_guard_uniq UNIQUE (name, guard_name)
);

CREATE TABLE role_has_permissions (
    permission_id ulid NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    role_id       ulid NOT NULL REFERENCES roles(id)       ON DELETE CASCADE,
    PRIMARY KEY (permission_id, role_id)
);

CREATE TABLE model_has_roles (
    role_id    ulid         NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    model_type varchar(255) NOT NULL,
    model_id   varchar(26)  NOT NULL,
    PRIMARY KEY (role_id, model_id, model_type)
);
CREATE INDEX model_has_roles_model_idx ON model_has_roles (model_id, model_type);

CREATE TABLE model_has_permissions (
    permission_id ulid         NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    model_type    varchar(255) NOT NULL,
    model_id      varchar(26)  NOT NULL,
    PRIMARY KEY (permission_id, model_id, model_type)
);
CREATE INDEX model_has_permissions_model_idx ON model_has_permissions (model_id, model_type);
```

`model_has_permissions` permite otorgar un permiso directo a un usuario sin pasar por un rol. No se usa en Fase 1, pero viene con el paquete y conviene dejarla: será útil para excepciones operativas puntuales.

---

# 7. Geografía

La jerarquía es `Country → Region → City → Location`, y su consistencia la garantiza el motor mediante claves foráneas compuestas. El patrón es: cada nivel expone una `UNIQUE (id, padre_id)` que el nivel inferior referencia junto con su propia FK.

## 7.1 `countries`

```sql
CREATE TABLE countries (
    id         ulid PRIMARY KEY,
    name       varchar(150) NOT NULL,
    code       char(2)      NOT NULL,   -- ISO 3166-1 alpha-2: CR, GT, DO, VE
    dial_code  varchar(5)   NOT NULL,   -- +506, +502, +1809, +58
    slug       varchar(180) NOT NULL,   -- costa-rica, guatemala

    status     varchar(20) NOT NULL DEFAULT 'inactive'
               CONSTRAINT countries_status_chk
               CHECK (status IN ('active','inactive')),

    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT countries_code_uniq UNIQUE (code),
    CONSTRAINT countries_slug_uniq UNIQUE (slug)
);
```

**`dial_code` está justificado hoy**, no es un campo "por si acaso": el importador necesita el prefijo del país para normalizar teléfonos a E.164, y esa normalización es una de las señales de deduplicación.

**`timezone` no se incluye.** Lo necesitará Fase 3 (disponibilidad y reservas), y añadir una columna a un catálogo de cuatro filas entonces es trivial. Documentado aquí para que no se olvide.

El slug es la palabra completa (`costa-rica`), no el código. Tiene valor SEO real en consultas del tipo "cardiólogo en Costa Rica", y `code` sigue disponible para la lógica interna.

Estado por defecto `inactive`: un país se activa cuando su carga de datos está lista, no cuando se inserta la fila.

## 7.2 `regions`

```sql
CREATE TABLE regions (
    id         ulid PRIMARY KEY,
    country_id ulid NOT NULL REFERENCES countries(id) ON DELETE RESTRICT,
    name       varchar(150) NOT NULL,
    slug       varchar(180) NOT NULL,

    status     varchar(20) NOT NULL DEFAULT 'active'
               CONSTRAINT regions_status_chk
               CHECK (status IN ('active','inactive')),

    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT regions_country_slug_uniq UNIQUE (country_id, slug),
    CONSTRAINT regions_id_country_uniq   UNIQUE (id, country_id)   -- destino de FK compuesta
);

CREATE INDEX regions_country_idx ON regions (country_id);
```

## 7.3 `cities`

```sql
CREATE TABLE cities (
    id         ulid PRIMARY KEY,
    country_id ulid NOT NULL,           -- denormalizado: la URL lo exige
    region_id  ulid NOT NULL,
    name       varchar(150) NOT NULL,
    slug       varchar(180) NOT NULL,

    status     varchar(20) NOT NULL DEFAULT 'active'
               CONSTRAINT cities_status_chk
               CHECK (status IN ('active','inactive')),

    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),

    -- el slug de ciudad viaja en la URL dentro del ámbito país
    CONSTRAINT cities_country_slug_uniq UNIQUE (country_id, slug),
    CONSTRAINT cities_id_region_uniq    UNIQUE (id, region_id),
    CONSTRAINT cities_id_country_uniq   UNIQUE (id, country_id),

    -- garantiza a nivel de motor que la región de la ciudad pertenece a su país
    CONSTRAINT cities_region_country_fk
        FOREIGN KEY (region_id, country_id)
        REFERENCES regions (id, country_id) ON DELETE RESTRICT
);

CREATE INDEX cities_region_idx ON cities (region_id);
```

**El caso que esto resuelve:** en Costa Rica existe más de un "San José" en provincias distintas. Con unicidad solo por región, dos ciudades legítimas producirían la URL `/costa-rica/cardiologos/san-jose` y una de las dos páginas sería inalcanzable. Con `UNIQUE (country_id, slug)`, la base de datos te obliga a desambiguar el slug (por ejemplo `san-jose-alajuela`) en el momento de la carga, en vez de que lo descubras en producción.

## 7.4 `city_aliases`

```sql
CREATE TABLE city_aliases (
    id               ulid PRIMARY KEY,
    city_id          ulid NOT NULL REFERENCES cities(id) ON DELETE CASCADE,
    country_id       ulid NOT NULL REFERENCES countries(id) ON DELETE RESTRICT,

    alias            varchar(150) NOT NULL,   -- tal como venía en la fuente
    alias_normalized varchar(150) NOT NULL,   -- minúsculas, sin acentos, escrito por la app

    source           varchar(20) NOT NULL DEFAULT 'import'
                     CONSTRAINT city_aliases_source_chk
                     CHECK (source IN ('import','admin')),

    created_at       timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT city_aliases_country_alias_uniq UNIQUE (country_id, alias_normalized)
);
```

Cada vez que un admin resuelve manualmente un "San Jose Centro" o un "SJ, CR" que el importador no supo mapear, se graba el alias. El siguiente lote lo resuelve solo. Es lo que hace que el esfuerzo de importación decrezca en vez de repetirse.

---

# 8. Catálogos

## 8.1 `specialties`

```sql
CREATE TABLE specialties (
    id          ulid PRIMARY KEY,
    parent_id   ulid NULL REFERENCES specialties(id) ON DELETE RESTRICT,

    name        varchar(150) NOT NULL,
    slug        varchar(180) NOT NULL,
    description text NULL,

    status      varchar(20) NOT NULL DEFAULT 'active'
                CONSTRAINT specialties_status_chk
                CHECK (status IN ('active','inactive')),

    created_at  timestamptz NOT NULL DEFAULT now(),
    updated_at  timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT specialties_slug_uniq UNIQUE (slug),
    CONSTRAINT specialties_no_self_parent_chk CHECK (parent_id IS NULL OR parent_id <> id)
);

CREATE INDEX specialties_parent_idx ON specialties (parent_id);
```

El catálogo es **global, no por país**. Cardiología es cardiología en los cuatro mercados, y un catálogo por país multiplicaría el mantenimiento y rompería las páginas SEO transversales. Las diferencias de nomenclatura local se resuelven con `specialty_aliases`.

`parent_id` permite una jerarquía de dos niveles (Cardiología → Cardiología intervencionista). El `CHECK` evita el autopadre; ciclos más largos son teóricamente posibles y se previenen en la aplicación, pero con un catálogo curado manualmente el riesgo es nulo.

> **Decisión de URL pendiente que depende de esto:** si la subespecialidad tendrá página propia (`/costa-rica/cardiologia-intervencionista/san-jose`) o solo será un filtro dentro de la página de la especialidad padre. Es una decisión de SEO, no de base de datos, pero conviene tomarla antes de generar el sitemap.

## 8.2 `specialty_aliases`

```sql
CREATE TABLE specialty_aliases (
    id               ulid PRIMARY KEY,
    specialty_id     ulid NOT NULL REFERENCES specialties(id) ON DELETE CASCADE,

    alias            varchar(150) NOT NULL,
    alias_normalized varchar(150) NOT NULL,

    kind             varchar(20) NOT NULL DEFAULT 'import'
                     CONSTRAINT specialty_aliases_kind_chk
                     CHECK (kind IN ('import','seo','local')),

    created_at       timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT specialty_aliases_alias_uniq UNIQUE (alias_normalized)
);
```

`kind` distingue tres usos distintos de la misma tabla: `import` para mapear texto libre de la fuente ("CARDIOLOGIA CLINICA"), `local` para variantes por país, y `seo` para sinónimos de búsqueda ("médico del corazón"). Este último no se explota en Fase 1 pero la estructura lo admite sin cambios.

## 8.3 `languages`

```sql
CREATE TABLE languages (
    id         ulid PRIMARY KEY,
    code       varchar(5)  NOT NULL,   -- ISO 639-1: es, en, fr, pt
    name       varchar(100) NOT NULL,

    status     varchar(20) NOT NULL DEFAULT 'active'
               CONSTRAINT languages_status_chk
               CHECK (status IN ('active','inactive')),

    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT languages_code_uniq UNIQUE (code)
);
```

---

# 9. Núcleo del directorio

## 9.1 `doctors`

```sql
CREATE TABLE doctors (
    id                   ulid PRIMARY KEY,

    country_id           ulid NOT NULL REFERENCES countries(id) ON DELETE RESTRICT,
    user_id              ulid NULL     REFERENCES users(id)     ON DELETE RESTRICT,
    created_by_user_id   ulid NULL     REFERENCES users(id)     ON DELETE SET NULL,

    first_name           varchar(100) NOT NULL,
    last_name            varchar(150) NOT NULL,
    professional_name    varchar(200) NULL,

    slug                 varchar(220) NOT NULL,
    name_normalized      varchar(191) NOT NULL,   -- escrita por la app, base del matching

    gender               varchar(20) NULL
                         CONSTRAINT doctors_gender_chk
                         CHECK (gender IN ('male','female','other','undisclosed')),

    -- licencia profesional
    license_number       varchar(100) NULL,
    license_source       varchar(30) NULL
                         CONSTRAINT doctors_license_source_chk
                         CHECK (license_source IN
                             ('official_registry','import_third_party','self_declared','admin','claim')),
    license_verified_at  timestamptz NULL,

    -- publicación
    status               varchar(20) NOT NULL DEFAULT 'draft'
                         CONSTRAINT doctors_status_chk
                         CHECK (status IN ('draft','active','inactive','suspended','merged')),
    published_at         timestamptz NULL,

    -- verificación profesional
    verification_status  varchar(20) NOT NULL DEFAULT 'unverified'
                         CONSTRAINT doctors_verification_status_chk
                         CHECK (verification_status IN ('unverified','pending','verified','rejected')),
    verification_source  varchar(30) NULL
                         CONSTRAINT doctors_verification_source_chk
                         CHECK (verification_source IN ('official_registry','document','manual','claim')),
    verified_at          timestamptz NULL,
    verified_by_user_id  ulid NULL REFERENCES users(id) ON DELETE SET NULL,

    -- reclamación
    claim_status         varchar(20) NOT NULL DEFAULT 'unclaimed'
                         CONSTRAINT doctors_claim_status_chk
                         CHECK (claim_status IN ('unclaimed','pending','claimed','rejected')),
    claimed_at           timestamptz NULL,

    -- procedencia y fusión
    source               varchar(20) NOT NULL DEFAULT 'admin'
                         CONSTRAINT doctors_source_chk
                         CHECK (source IN ('import','admin','claim')),
    import_batch_id      ulid NULL,   -- FK añadida tras crear import_batches (§12.1)
    merged_into_doctor_id ulid NULL REFERENCES doctors(id) ON DELETE RESTRICT,

    -- búsqueda
    search_vector tsvector GENERATED ALWAYS AS (
        to_tsvector('es_unaccent',
            coalesce(first_name,'') || ' ' ||
            coalesce(last_name,'')  || ' ' ||
            coalesce(professional_name,'')
        )
    ) STORED,

    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),

    -- invariantes de estado
    CONSTRAINT doctors_merged_requires_target_chk
        CHECK (status <> 'merged' OR merged_into_doctor_id IS NOT NULL),
    CONSTRAINT doctors_not_merged_into_self_chk
        CHECK (merged_into_doctor_id IS NULL OR merged_into_doctor_id <> id),
    CONSTRAINT doctors_claimed_requires_user_chk
        CHECK (claim_status <> 'claimed' OR user_id IS NOT NULL),
    CONSTRAINT doctors_verified_requires_trace_chk
        CHECK (verification_status <> 'verified'
               OR (verified_at IS NOT NULL AND verification_source IS NOT NULL)),
    CONSTRAINT doctors_published_requires_active_chk
        CHECK (published_at IS NULL OR status <> 'draft')
);
```

Índices:

```sql
-- un usuario administra como máximo un médico
CREATE UNIQUE INDEX doctors_user_uniq
    ON doctors (user_id) WHERE user_id IS NOT NULL;

-- URL pública: /{pais}/medicos/{slug}
CREATE UNIQUE INDEX doctors_country_slug_uniq
    ON doctors (country_id, slug);

-- clave natural fuerte cuando existe licencia
CREATE UNIQUE INDEX doctors_country_license_uniq
    ON doctors (country_id, license_number) WHERE license_number IS NOT NULL;

-- listados públicos: solo fichas publicadas
CREATE INDEX doctors_public_idx
    ON doctors (country_id, last_name, first_name) WHERE status = 'active';

-- matching difuso de nombres en la importación
CREATE INDEX doctors_name_trgm_gin
    ON doctors USING gin (name_normalized gin_trgm_ops);

-- búsqueda textual
CREATE INDEX doctors_search_gin
    ON doctors USING gin (search_vector);

-- colas de trabajo del backoffice
CREATE INDEX doctors_claim_pending_idx
    ON doctors (claim_status) WHERE claim_status = 'pending';
CREATE INDEX doctors_verification_pending_idx
    ON doctors (verification_status) WHERE verification_status = 'pending';

CREATE INDEX doctors_created_by_idx ON doctors (created_by_user_id);
CREATE INDEX doctors_merged_into_idx ON doctors (merged_into_doctor_id)
    WHERE merged_into_doctor_id IS NOT NULL;
```

### Notas de diseño

**`country_id` es obligatorio y se fija en la creación.** Un médico con consultas en dos países (caso raro pero real en zonas fronterizas) se modela con `country_id` = país principal y ubicaciones en ambos. La ficha vive en una sola URL de país; la ubicación extranjera aparece en la ficha pero no genera página de listado en el otro país. Es una limitación consciente y aceptable en Fase 1.

**`user_id` con `ON DELETE RESTRICT`.** Si algún día hay que eliminar de verdad la cuenta de un médico, primero hay que desvincular el perfil explícitamente. Es preferible a que un borrado deje la ficha en un estado que el `CHECK` de `claim_status` rechazaría.

**`name_normalized` la escribe PHP, no SQL.** La normalización incluye pasar a minúsculas, eliminar acentos, quitar títulos (`dr`, `dra`, `lic`, `md`) y ordenar tokens. Ordenar tokens en SQL es incómodo y frágil; en PHP son diez líneas. La columna alimenta el índice GIN trigram, que es la base de la detección de duplicados:

```sql
SELECT id, name_normalized, similarity(name_normalized, :needle) AS score
FROM doctors
WHERE country_id = :country
  AND status <> 'merged'
  AND name_normalized % :needle
ORDER BY score DESC
LIMIT 10;
```

**`search_vector` no incluye especialidades ni ciudad**, porque una columna generada solo puede leer su propia fila. En Fase 1, buscar "cardiólogo en San José" se resuelve combinando el filtro por `specialty_id` y `city_id` con el `tsvector` del nombre, que es suficiente. Cuando la búsqueda necesite un único índice sobre todo el texto (Fase 2), la solución es una vista materializada `doctor_search_index` o el paso a Meilisearch. Nada de esto obliga a cambiar el núcleo.

**Los `CHECK` de invariantes son deliberados.** No impiden todos los estados inválidos (eso es imposible sin triggers), pero sí los cuatro que un bug de la aplicación produciría con más facilidad. Cuestan cero en escritura y convierten un error silencioso de datos en una excepción inmediata.

## 9.2 `doctor_profiles`

```sql
CREATE TABLE doctor_profiles (
    id                 ulid PRIMARY KEY,
    doctor_id          ulid NOT NULL REFERENCES doctors(id) ON DELETE CASCADE,

    headline           varchar(255) NULL,
    bio                text NULL,
    education          text NULL,
    experience         text NULL,
    profile_photo_path varchar(500) NULL,

    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT doctor_profiles_doctor_uniq UNIQUE (doctor_id)
);
```

**Condición para mantener esta tabla separada:** la fila debe crearse siempre en la misma transacción que el `Doctor`, sin excepción. Si no se garantiza, cada consulta del sitio público necesita `LEFT JOIN` y comprobación de nulos, y el beneficio de mantener `doctors` estrecha se pierde en complejidad. La invariante vive en `CreateDoctorAction` (§11).

**Deuda técnica aceptada:** `education` y `experience` son texto libre. Cuando los perfiles premium quieran entradas estructuradas (título, institución, año), habrá que parsear texto. Es una decisión consciente: estructurarlo hoy significaría pedir al importador datos que la fuente no trae.

## 9.3 `doctor_specialties`

```sql
CREATE TABLE doctor_specialties (
    doctor_id    ulid NOT NULL REFERENCES doctors(id)     ON DELETE CASCADE,
    specialty_id ulid NOT NULL REFERENCES specialties(id) ON DELETE RESTRICT,
    is_primary   boolean NOT NULL DEFAULT false,
    created_at   timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (doctor_id, specialty_id)
);

CREATE INDEX doctor_specialties_specialty_idx ON doctor_specialties (specialty_id);

-- máximo una especialidad primaria por médico, garantizado por el motor
CREATE UNIQUE INDEX doctor_specialties_one_primary_uniq
    ON doctor_specialties (doctor_id) WHERE is_primary;
```

Esa última línea es uno de los motivos del cambio a PostgreSQL. En MySQL requería una columna generada y un índice único sobre ella; aquí es una línea y la invariante queda en el motor en lugar de depender de que ninguna ruta de escritura futura se olvide de la transacción.

## 9.4 `doctor_languages`

```sql
CREATE TABLE doctor_languages (
    doctor_id   ulid NOT NULL REFERENCES doctors(id)    ON DELETE CASCADE,
    language_id ulid NOT NULL REFERENCES languages(id)  ON DELETE RESTRICT,
    created_at  timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (doctor_id, language_id)
);

CREATE INDEX doctor_languages_language_idx ON doctor_languages (language_id);
```

El filtro "médicos que hablan inglés" es un `JOIN` indexado, no un escaneo de JSON.

## 9.5 `locations`

```sql
CREATE TABLE locations (
    id                 ulid PRIMARY KEY,

    country_id         ulid NOT NULL,
    region_id          ulid NOT NULL,
    city_id            ulid NOT NULL,

    name               varchar(200) NULL,        -- "Torre Médica Momentum, piso 4"
    address            varchar(255) NOT NULL,
    address_2          varchar(255) NULL,
    postal_code        varchar(30)  NULL,
    address_normalized varchar(300) NOT NULL,    -- escrita por la app, para deduplicar

    latitude           numeric(10,8) NULL,
    longitude          numeric(11,8) NULL,

    status             varchar(20) NOT NULL DEFAULT 'active'
                       CONSTRAINT locations_status_chk
                       CHECK (status IN ('active','inactive')),

    created_by_user_id ulid NULL REFERENCES users(id) ON DELETE SET NULL,

    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT locations_city_region_fk
        FOREIGN KEY (city_id, region_id)
        REFERENCES cities (id, region_id) ON DELETE RESTRICT,

    CONSTRAINT locations_region_country_fk
        FOREIGN KEY (region_id, country_id)
        REFERENCES regions (id, country_id) ON DELETE RESTRICT,

    CONSTRAINT locations_coordinates_chk
        CHECK ((latitude IS NULL) = (longitude IS NULL))
);

CREATE INDEX locations_city_idx ON locations (city_id);
CREATE INDEX locations_country_idx ON locations (country_id);
CREATE INDEX locations_address_norm_idx ON locations (city_id, address_normalized);
```

**No hay índice sobre `(latitude, longitude)`.** Un B-tree compuesto solo aprovecharía el prefijo de latitud en una consulta de caja, así que no sirve para "cerca de mí" y sí cuesta en cada escritura. En Fase 3 se añade PostGIS con una columna generada y un índice GiST:

```sql
-- Fase 3, no ejecutar ahora
ALTER TABLE locations ADD COLUMN geo geography(Point,4326)
    GENERATED ALWAYS AS (
        CASE WHEN longitude IS NOT NULL
        THEN ST_SetSRID(ST_MakePoint(longitude, latitude),4326)::geography END
    ) STORED;
CREATE INDEX locations_geo_gist ON locations USING gist (geo);
```

**`address_normalized`** existe para que el importador detecte que "Av. Central 123" y "Avenida Central #123" en la misma ciudad son la misma dirección. No es único: dos consultorios pueden compartir edificio. Es una señal para el proceso de revisión, no una restricción.

**Las dos FK compuestas** hacen imposible insertar una ubicación cuya ciudad no pertenezca a su región, o cuya región no pertenezca a su país. Es la validación que la v1.0 delegaba a la aplicación.

## 9.6 `doctor_locations`

```sql
CREATE TABLE doctor_locations (
    doctor_id     ulid NOT NULL REFERENCES doctors(id)   ON DELETE CASCADE,
    location_id   ulid NOT NULL REFERENCES locations(id) ON DELETE RESTRICT,

    location_type varchar(20) NOT NULL DEFAULT 'office'
                  CONSTRAINT doctor_locations_type_chk
                  CHECK (location_type IN ('office','clinic','hospital','other')),

    is_primary    boolean NOT NULL DEFAULT false,
    created_at    timestamptz NOT NULL DEFAULT now(),

    PRIMARY KEY (doctor_id, location_id)
);

CREATE INDEX doctor_locations_location_idx ON doctor_locations (location_id);

CREATE UNIQUE INDEX doctor_locations_one_primary_uniq
    ON doctor_locations (doctor_id) WHERE is_primary;
```

**Decisión pendiente de confirmar:** la relación es N:N, lo que significa que una dirección puede ser compartida por varios médicos (el caso de la torre médica). El riesgo es que un médico edite una dirección compartida con otros veinte. La regla operativa de Fase 1 es: **una ubicación con más de un médico asociado solo la puede editar un admin.** Es una Policy, no una restricción de base de datos. Cuando llegue la entidad `clinics` en V2, la ubicación pasará a pertenecer a la clínica y la regla desaparece.

## 9.7 `doctor_contacts`

```sql
CREATE TABLE doctor_contacts (
    id               ulid PRIMARY KEY,
    doctor_id        ulid NOT NULL REFERENCES doctors(id)   ON DELETE CASCADE,
    location_id      ulid NULL     REFERENCES locations(id) ON DELETE SET NULL,

    type             varchar(20) NOT NULL
                     CONSTRAINT doctor_contacts_type_chk
                     CHECK (type IN ('phone','mobile','whatsapp','email','website')),

    value            varchar(255) NOT NULL,   -- tal como se muestra
    value_normalized varchar(255) NOT NULL,   -- E.164 para teléfonos, minúsculas para email
    label            varchar(100) NULL,       -- "Consultorio", "Emergencias"

    is_public        boolean NOT NULL DEFAULT true,
    is_primary       boolean NOT NULL DEFAULT false,
    verified_at      timestamptz NULL,

    source           varchar(20) NOT NULL DEFAULT 'admin'
                     CONSTRAINT doctor_contacts_source_chk
                     CHECK (source IN ('import','admin','doctor')),

    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT doctor_contacts_value_uniq UNIQUE (doctor_id, type, value_normalized)
);

CREATE UNIQUE INDEX doctor_contacts_one_primary_uniq
    ON doctor_contacts (doctor_id, type) WHERE is_primary;

CREATE INDEX doctor_contacts_normalized_idx ON doctor_contacts (value_normalized);
CREATE INDEX doctor_contacts_location_idx   ON doctor_contacts (location_id);
```

`location_id` nullable resuelve el caso real del médico con dos consultorios y dos teléfonos distintos. El teléfono general del profesional lo deja en `NULL`.

`value_normalized` en E.164 sirve simultáneamente para el enlace `tel:`, para el deep link de WhatsApp y como señal de deduplicación en el importador. El índice sobre esa columna existe por lo tercero.

**Nota de producto, no de base de datos:** el documento funcional del MVP decía que los datos de contacto solo serían visibles para pacientes registrados o para médicos con suscripción. En Fase 1 no existen ni pacientes ni suscripciones, así que el contacto será público. Gatear después algo que ya era público es una regresión visible para el usuario y para Google. La recomendación es mantenerlo público de forma permanente para el plan gratuito y monetizar la visibilidad, el posicionamiento y las herramientas de productividad, no el teléfono.

## 9.8 `doctor_external_references`

```sql
CREATE TABLE doctor_external_references (
    id            ulid PRIMARY KEY,
    doctor_id     ulid NOT NULL REFERENCES doctors(id) ON DELETE CASCADE,

    source        varchar(50)  NOT NULL,   -- 'colegio_medicos_cr', 'padron_gt'
    reference     varchar(100) NOT NULL,   -- identificador en la fuente

    first_seen_at timestamptz NOT NULL,
    last_seen_at  timestamptz NOT NULL,
    created_at    timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT doctor_external_refs_uniq UNIQUE (source, reference)
);

CREATE INDEX doctor_external_refs_doctor_idx ON doctor_external_references (doctor_id);
```

Es una tabla 1:N y no un par de columnas en `doctors` por un motivo concreto: **al fusionar dos fichas duplicadas, el superviviente debe heredar las referencias externas de ambas.** Si no, el siguiente lote de importación no reconoce la referencia huérfana y recrea el duplicado que se acababa de eliminar.

`last_seen_at` indica qué fichas dejaron de aparecer en la fuente, que es la señal para revisar bajas (médico retirado, fallecido o trasladado).

---

# 10. Claim

## 10.1 `doctor_claims`

```sql
CREATE TABLE doctor_claims (
    id                     ulid PRIMARY KEY,
    doctor_id              ulid NOT NULL REFERENCES doctors(id) ON DELETE RESTRICT,
    user_id                ulid NOT NULL REFERENCES users(id)   ON DELETE RESTRICT,

    status                 varchar(20) NOT NULL DEFAULT 'pending'
                           CONSTRAINT doctor_claims_status_chk
                           CHECK (status IN ('pending','approved','rejected','cancelled')),

    claimed_license_number varchar(100) NULL,
    contact_email          varchar(255) NULL,
    contact_phone          varchar(30)  NULL,
    evidence_path          varchar(500) NULL,
    applicant_notes        text NULL,

    submitted_at           timestamptz NOT NULL DEFAULT now(),
    reviewed_by_user_id    ulid NULL REFERENCES users(id) ON DELETE SET NULL,
    reviewed_at            timestamptz NULL,
    resolution_reason      varchar(255) NULL,

    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT doctor_claims_resolved_requires_trace_chk
        CHECK (status = 'pending' OR reviewed_at IS NOT NULL OR status = 'cancelled')
);

-- un solo claim aprobado por médico, para siempre
CREATE UNIQUE INDEX doctor_claims_one_approved_uniq
    ON doctor_claims (doctor_id) WHERE status = 'approved';

-- un usuario no puede tener dos solicitudes abiertas sobre la misma ficha
CREATE UNIQUE INDEX doctor_claims_one_pending_per_user_uniq
    ON doctor_claims (doctor_id, user_id) WHERE status = 'pending';

CREATE INDEX doctor_claims_queue_idx ON doctor_claims (submitted_at) WHERE status = 'pending';
CREATE INDEX doctor_claims_user_idx  ON doctor_claims (user_id);
```

Los dos índices parciales imponen exactamente las dos reglas que importan, y **permiten deliberadamente varias solicitudes pendientes de usuarios distintos** sobre la misma ficha. Es el caso real de los homónimos, y lo resuelve un admin comparando evidencias.

`doctors.claim_status` pasa a ser un **estado cacheado**: lo escribe únicamente `ApproveClaimAction` o `RejectClaimAction`, dentro de la misma transacción. La fuente de verdad es esta tabla.

## 10.2 Flujo y reglas

```
M�dico encuentra su ficha
        ↓
Registro / login  (email_verified_at obligatorio)
        ↓
Solicita claim → doctor_claims(status = pending)
                 doctors.claim_status = 'pending'
        ↓
Revisión admin
        ↓
Aprobación (transacción única):
   claims.status          = 'approved'
   claims.reviewed_*      = admin, now()
   doctors.user_id        = solicitante
   doctors.claim_status   = 'claimed'
   doctors.claimed_at     = now()
   demás claims pendientes de esa ficha → 'rejected'
   si aportó licencia: doctors.license_number, license_source = 'claim'
                       verification_status = 'verified', verification_source = 'claim'
```

**Vía rápida cuando la ficha ya tiene licencia:** si `claimed_license_number` coincide con `doctors.license_number`, la revisión es mínima.

**Advertencia de seguridad.** El número de colegiado suele ser público, así que la coincidencia de licencia es condición necesaria pero **no suficiente**. Un atacante elegirá siempre fichas del grupo que sí tiene licencia registrada. Añade al menos una segunda señal antes de aprobar: correo del dominio del consultorio, o llamada al teléfono que ya figura en la ficha. Más `rate limiting` sobre el endpoint de solicitud.

**Alcance editable tras el claim.** Aprobar un claim no convierte al médico en administrador de su ficha completa. En Fase 1 puede editar `doctor_profiles` (headline, bio, foto), sus contactos y sus ubicaciones. **No** puede modificar `first_name`, `last_name`, `license_number`, `verification_status` ni sus especialidades sin revisión administrativa. Esto es una Policy de Laravel, y es lo que `profile.update` significa realmente.

---

# 11. Invariantes que la base de datos no puede imponer

Estas reglas viven en Actions de dominio, nunca en controladores, porque los comandos de consola y los jobs del importador también deben respetarlas.

| Action | Invariante |
|---|---|
| `CreateDoctorAction` | Crea siempre `doctor_profiles` en la misma transacción. Calcula `name_normalized` y `slug`. Rechaza si hay coincidencia en `doctor_suppressions` |
| `PublishDoctorAction` | Solo publica con ≥1 especialidad, ≥1 ubicación con ciudad válida y ≥1 contacto público. Fija `status='active'` y `published_at`. Purga la caché de CDN |
| `UnpublishDoctorAction` | `status='inactive'`, purga CDN, mantiene `published_at` como histórico |
| `ApproveClaimAction` | Transacción del §10.2 completa, incluido el rechazo de los claims rivales |
| `VerifyDoctorAction` | Exige `verification_source` y registra `verified_by_user_id` y `verified_at` |
| `MergeDoctorsAction` | §13. Manual, con confirmación explícita, nunca automática |
| `ApplyImportBatchAction` | Solo aplica filas en `matched`, `new` o `approved`. Nunca publica |
| `UpdateSlugAction` | Escribe `slug_redirects` antes de cambiar el slug. Nunca cambia un slug sin dejar el 301 |

**Regla de oro del repositorio:** si una de estas reglas aparece dentro de un controlador o de un recurso de Filament, es un bug. El único lugar correcto es la Action, porque el importador y la futura API móvil llaman a la misma.

---

# 12. Importación masiva

## 12.1 `import_batches`

```sql
CREATE TABLE import_batches (
    id                 ulid PRIMARY KEY,
    country_id         ulid NOT NULL REFERENCES countries(id) ON DELETE RESTRICT,
    created_by_user_id ulid NULL REFERENCES users(id) ON DELETE SET NULL,

    source             varchar(50)  NOT NULL,   -- 'colegio_medicos_cr'
    file_name          varchar(255) NOT NULL,
    file_hash          char(64)     NOT NULL,   -- sha256 del archivo
    file_path          varchar(500) NULL,

    status             varchar(20) NOT NULL DEFAULT 'ingesting'
                       CONSTRAINT import_batches_status_chk
                       CHECK (status IN
                           ('ingesting','normalizing','matching','review','applying','completed','failed')),

    rows_total    integer NOT NULL DEFAULT 0,
    rows_new      integer NOT NULL DEFAULT 0,
    rows_matched  integer NOT NULL DEFAULT 0,
    rows_review   integer NOT NULL DEFAULT 0,
    rows_applied  integer NOT NULL DEFAULT 0,
    rows_skipped  integer NOT NULL DEFAULT 0,
    rows_failed   integer NOT NULL DEFAULT 0,

    started_at  timestamptz NULL,
    finished_at timestamptz NULL,
    notes       text NULL,

    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT import_batches_file_hash_uniq UNIQUE (source, file_hash)
);

-- FK diferida de doctors, ahora que existe la tabla destino
ALTER TABLE doctors
    ADD CONSTRAINT doctors_import_batch_fk
    FOREIGN KEY (import_batch_id) REFERENCES import_batches(id) ON DELETE SET NULL;
```

`UNIQUE (source, file_hash)` evita reprocesar dos veces el mismo archivo por error.

## 12.2 `import_rows`

```sql
CREATE TABLE import_rows (
    id          ulid PRIMARY KEY,
    batch_id    ulid NOT NULL REFERENCES import_batches(id) ON DELETE CASCADE,
    row_number  integer NOT NULL,

    raw_payload jsonb    NOT NULL,   -- la fila original, intacta
    row_hash    char(64) NOT NULL,   -- sha256 del payload crudo

    -- campos normalizados por el pipeline
    n_first_name    varchar(100) NULL,
    n_last_name     varchar(150) NULL,
    n_name_key      varchar(191) NULL,
    n_license       varchar(100) NULL,
    n_phone_e164    varchar(30)  NULL,
    n_email         varchar(255) NULL,
    n_country_id    ulid NULL REFERENCES countries(id) ON DELETE RESTRICT,
    n_city_id       ulid NULL REFERENCES cities(id)    ON DELETE RESTRICT,
    n_specialty_ids jsonb NULL,
    n_address       varchar(300) NULL,

    -- resultado del matching
    match_type varchar(20) NULL
               CONSTRAINT import_rows_match_type_chk
               CHECK (match_type IN
                   ('external_ref','license','phone','email','name_city','none')),
    match_confidence varchar(10) NOT NULL DEFAULT 'none'
               CONSTRAINT import_rows_confidence_chk
               CHECK (match_confidence IN ('strong','weak','none')),
    matched_doctor_id    ulid NULL REFERENCES doctors(id) ON DELETE SET NULL,
    candidate_doctor_ids jsonb NULL,

    status varchar(20) NOT NULL DEFAULT 'pending'
           CONSTRAINT import_rows_status_chk
           CHECK (status IN
               ('pending','normalized','matched','new','needs_review',
                'approved','applied','skipped','failed','suppressed')),

    validation_errors jsonb NULL,

    -- resolución humana
    resolution varchar(20) NULL
               CONSTRAINT import_rows_resolution_chk
               CHECK (resolution IN ('create_new','link_existing','discard')),
    resolved_by_user_id ulid NULL REFERENCES users(id) ON DELETE SET NULL,
    resolved_at         timestamptz NULL,

    applied_doctor_id ulid NULL REFERENCES doctors(id) ON DELETE SET NULL,
    applied_at        timestamptz NULL,

    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT import_rows_batch_hash_uniq UNIQUE (batch_id, row_hash)
);

CREATE INDEX import_rows_batch_status_idx ON import_rows (batch_id, status);
CREATE INDEX import_rows_review_idx       ON import_rows (batch_id) WHERE status = 'needs_review';
CREATE INDEX import_rows_license_idx      ON import_rows (n_country_id, n_license)
    WHERE n_license IS NOT NULL;
CREATE INDEX import_rows_namekey_trgm_gin ON import_rows USING gin (n_name_key gin_trgm_ops);
CREATE INDEX import_rows_payload_gin      ON import_rows USING gin (raw_payload);
```

Tres cosas que hace esta tabla y que no se consiguen de otro modo:

- **`raw_payload` intacto** permite reprocesar el lote completo cuando se descubra que el mapeo de ciudades estaba mal, sin volver a pedir el archivo.
- **`UNIQUE (batch_id, row_hash)`** hace el ingest idempotente: si el job se cae a la mitad y reintenta, no duplica filas.
- **`applied_doctor_id`** da trazabilidad inversa: qué ficha nació de qué fila de qué archivo. Es lo que permite revertir un lote concreto.

`import_rows` es una tabla operativa. Una vez consolidado un lote se puede purgar, conservando `import_batches` y `doctor_external_references`, que son el registro permanente.

## 12.2b Formato de entrada: la plantilla

El formato canónico de entrada es la plantilla Excel que genera `php artisan import:template {país}`. Las fuentes externas (colegios, padrones) se convierten a este formato.

- **El contrato de columnas vive en código**, en `App\Domain\Import\Template\TemplateColumns`. Lo usan el generador, el lector de `import:ingest` y los tests.
- **Una fila por consultorio.** Las filas con el mismo "ID del médico" son el mismo médico: sus datos se toman de la primera fila, y una contradicción manda la fila a revisión.
- **El "ID del médico" se guarda como `doctor_external_references`.** Si se mantiene igual entre archivos, recargar actualiza la ficha en lugar de duplicarla: es el nivel 1 del matching.
- **Un archivo por país.** La hoja oculta `_meta` declara el país y la versión de la plantilla, y el importador rechaza un archivo ajeno.
- **Las listas desplegables** (especialidades, ciudades, regiones, género, tipo) salen del catálogo al generar y funcionan en modo "aviso": un valor fuera de lista no se bloquea, va a revisión.

## 12.3 El pipeline, en cinco comandos

Son cinco comandos y no uno para que cada etapa sea reejecutable y verificable por separado.

```
php artisan import:ingest    {archivo} --country=CR --source=colegio_medicos_cr
php artisan import:normalize {batch}
php artisan import:match     {batch}
php artisan import:apply     {batch}
php artisan import:publish   {batch}
```

**1. `ingest`** — Crea el `import_batch`, vuelca las filas crudas en `import_rows`. No normaliza, no valida, no toca `doctors`.

**2. `normalize`** — Rellena los campos `n_*`. Aquí ocurre el trabajo sucio: mapear texto libre a `city_id` (usando `city_aliases`), a `specialty_id` (usando `specialty_aliases`), parsear teléfonos a E.164 con el `dial_code` del país, separar nombres, calcular `n_name_key`. Lo que no mapea queda en `needs_review` con `validation_errors`; **nunca se inventa un valor**.

> Este paso es el 70% del esfuerzo real de la importación, muy por encima de la detección de duplicados. Cada resolución manual debe grabar un alias, para que el siguiente lote requiera la mitad de intervención.

**3. `match`** — Consulta primero `doctor_suppressions`; si hay coincidencia, la fila queda `suppressed` y no se aplica nunca. Después aplica la cascada:

| Nivel | Señal | Confianza | Resultado |
|---|---|---|---|
| 1 | `(source, reference)` en `doctor_external_references` | fuerte | `matched` → actualiza |
| 2 | `(country_id, license_number)` | fuerte | `matched` → actualiza |
| 3 | teléfono E.164 o email coincidente | débil | `needs_review` |
| 4 | `similarity(name_normalized, n_name_key)` sobre umbral, misma ciudad | débil | `needs_review` |
| 5 | ninguna | ninguna | `new` → crea |

**4. `apply`** — Solo procesa filas en `matched`, `new` y `approved`. Las `needs_review` sin resolver **nunca se aplican**. Crea en `status = 'draft'`. Escribe `doctor_external_references` y `import_batch_id`.

**5. `publish`** — Comando aparte, deliberadamente. Aplica la puerta de calidad de `PublishDoctorAction` fila por fila y reporta cuántas no pasaron y por qué. **`apply` nunca publica.**

Todo el pipeline corre en colas, en chunks. Un lote de 20.000 filas no se
procesa en una petición HTTP. Ver ARQUITECTURA.md §8 para el runtime de colas.

**Implementación** (`app/Domain/Import/Actions/`):
- **Una Action por etapa,** más dos para la revisión humana:
  - `ResolveImportRowAction`: crear nueva, vincular o descartar, aplicado al médico completo.
  - `MapImportValueAction`: asignar una ciudad o especialidad del catálogo y grabar el alias.
- **El backoffice** (Operación → Importación) encola leer, normalizar y buscar coincidencias, y deja el lote "En revisión". Aplicar y Publicar son botones aparte.
- **Una coincidencia fuerte solo añade lo que falta.** Un valor distinto se anota como conflicto en `validation_errors` y nunca sobrescribe.
- **Las incidencias de cada fila** viven en `validation_errors` con tres niveles:
  - **error:** bloquea hasta que alguien decida o corrija;
  - **conflicto:** no se sobrescribió un valor de la ficha;
  - **aviso:** se omitió un dato secundario, por ejemplo un teléfono inválido.

## 12.4 El principio que gobierna el matching

**Un duplicado es un error barato. Una fusión incorrecta es un error caro e irreversible.**

Dos fichas del mismo cardiólogo se ven, molestan, y un admin las arregla en un minuto. Fusionar dos médicos distintos que se llaman igual mezcla especialidades, direcciones y teléfonos de dos profesionales reales: el paciente llama al número equivocado y reconstruir qué dato era de quién ya no es posible.

De ahí sale toda la política: **solo se fusiona automáticamente con evidencia fuerte; todo lo demás se duplica y se marca para revisión.** El diseño prefiere deliberadamente los falsos negativos.

Consecuencia conocida: la normalización por tokens fallará con nombres compuestos ("Juan Carlos Pérez Rodríguez" contra "Juan Pérez Rodríguez") y generará duplicados. Es el lado correcto del error, y el índice trigram del nivel 4 los captura como candidatos a revisión.

## 12.5 Perfilado previo, antes de escribir la primera migración

Sobre el archivo real, mide:

1. % de filas con colegiado no vacío y con formato válido.
2. % de colegiados duplicados dentro del propio archivo (indica si la fuente ya viene sucia).
3. % de teléfonos parseables a E.164.
4. Número de ciudades distintas en texto libre, y cuántas mapean automáticamente.
5. Número de especialidades distintas en texto libre.
6. % de nombres que colisionan entre sí dentro del mismo país.

El punto 6 dimensiona la revisión manual. Con un 3% sobre 20.000 médicos son 600 revisiones, gestionable. Con un 15% hay que replantear la estrategia: importar primero solo el subconjunto con colegiado y dejar el resto para una segunda fase de enriquecimiento.

---

# 13. Fusión de duplicados

`MergeDoctorsAction`, en una sola transacción:

1. Mueve al superviviente: `doctor_external_references`, `doctor_specialties`, `doctor_languages`, `doctor_locations`, `doctor_contacts`, `doctor_claims`.
2. Escribe `slug_redirects` del slug perdedor hacia el ganador (301, nunca 404).
3. Marca el perdedor: `status = 'merged'`, `merged_into_doctor_id = ganador`.
4. Registra en `activity_log` el estado completo previo de **ambos** registros.
5. Purga la caché de CDN de ambas URLs.

El registro perdedor **no se borra**. Es lo que impide que el siguiente lote lo recree y lo que mantiene válida cualquier URL ya indexada.

**No existe operación inversa automática.** Si un admin fusiona por error dos médicos distintos, deshacerlo es trabajo manual con el `activity_log` en la mano. Por eso la fusión en Fase 1 es exclusivamente manual y con confirmación explícita; el importador jamás la ejecuta.

---

# 14. Operación

## 14.1 `doctor_suppressions`

```sql
CREATE TABLE doctor_suppressions (
    id         ulid PRIMARY KEY,
    country_id ulid NOT NULL REFERENCES countries(id) ON DELETE RESTRICT,

    license_number   varchar(100) NULL,
    email_normalized varchar(255) NULL,
    phone_normalized varchar(30)  NULL,
    name_normalized  varchar(191) NULL,

    reason       varchar(255) NULL,
    requested_at timestamptz NOT NULL,

    created_by_user_id ulid NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT doctor_suppressions_has_key_chk
        CHECK (license_number IS NOT NULL
            OR email_normalized IS NOT NULL
            OR phone_normalized IS NOT NULL
            OR name_normalized  IS NOT NULL)
);

CREATE INDEX doctor_suppressions_license_idx ON doctor_suppressions (country_id, license_number);
CREATE INDEX doctor_suppressions_phone_idx   ON doctor_suppressions (phone_normalized);
CREATE INDEX doctor_suppressions_name_idx    ON doctor_suppressions (country_id, name_normalized);
```

Vas a publicar fichas de profesionales que nunca dieron su consentimiento; es la premisa del flujo de claim. Eso exige un canal de oposición y, sobre todo, **que la supresión sobreviva al siguiente lote de importación**. Sin esta tabla, marcar la ficha como `inactive` no basta: el import la recrea y tienes el mismo problema reputacional repetido.

> No soy abogado y el detalle debe validarse con asesoría local en cada mercado. Costa Rica tiene la Ley 8968 y su agencia (PRODHAB); República Dominicana, la Ley 172-13. A nivel de producto, este mecanismo es innegociable.

## 14.2 `slug_redirects`

```sql
CREATE TABLE slug_redirects (
    id          ulid PRIMARY KEY,
    entity_type varchar(30) NOT NULL
                CONSTRAINT slug_redirects_entity_chk
                CHECK (entity_type IN ('doctor','specialty','city','region')),
    entity_id   ulid NOT NULL,
    country_id  ulid NULL REFERENCES countries(id) ON DELETE RESTRICT,

    old_slug    varchar(220) NOT NULL,
    created_at  timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT slug_redirects_uniq UNIQUE NULLS NOT DISTINCT (entity_type, country_id, old_slug)
);
```

**`NULLS NOT DISTINCT` es obligatorio** (PostgreSQL 15+). En un `UNIQUE` normal, dos filas con `country_id` NULL no se consideran iguales, así que dos redirecciones del mismo slug de especialidad pasarían sin error.

La resolución es: la ruta pública no encuentra el slug, busca aquí, obtiene la entidad, y responde **301** hacia su slug actual. Nunca 404.

`country_id` es nullable porque `specialty` tiene slug global. Para `doctor` y `city` es obligatorio a nivel de aplicación.

En un import masivo vas a corregir nombres mal escritos durante meses. Cada corrección de slug pasa por `UpdateSlugAction`, que escribe aquí antes de tocar nada.

## 14.3 `doctor_contact_events`

```sql
CREATE TABLE doctor_contact_events (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    doctor_id    ulid NOT NULL REFERENCES doctors(id) ON DELETE CASCADE,
    country_id   ulid NOT NULL REFERENCES countries(id) ON DELETE RESTRICT,

    contact_type varchar(20) NOT NULL
                 CONSTRAINT contact_events_type_chk
                 CHECK (contact_type IN ('phone','whatsapp','email','website','directions')),

    source       varchar(20) NOT NULL DEFAULT 'profile'
                 CONSTRAINT contact_events_source_chk
                 CHECK (source IN ('profile','listing','map')),

    session_hash char(64) NULL,   -- hash con sal, sin PII
    occurred_at  timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX contact_events_doctor_time_idx ON doctor_contact_events (doctor_id, occurred_at DESC);
CREATE INDEX contact_events_time_idx        ON doctor_contact_events (occurred_at);
```

**Es la única tabla que no usa ULID.** Es append-only y de alto volumen; un `bigint` de identidad ocupa 8 bytes frente a 26 y mantiene los índices compactos. La trazabilidad de negocio la da `doctor_id`, no el identificador del evento.

Toda tu tesis de venta premium se apoya en poder decirle al médico "te contactaron 47 veces este mes". Eso no se puede reconstruir hacia atrás y GA4 no te lo da desglosado por médico dentro del producto.

Cuando el volumen lo justifique (Fase 3), se particiona por mes. `session_hash` no debe permitir reidentificar al visitante.

## 14.4 `activity_log`

Del paquete `spatie/laravel-activitylog`. No se publica el stub; la migración propia lo reproduce con tres ajustes:

- `subject_id` / `causer_id` en `varchar(26)`, para ULID.
- `created_at` / `updated_at` en `timestamptz` (§3.3).
- `attribute_changes` / `properties` en `jsonb` en lugar de `json`. El modelo `Activity` las castea a `collection`, así que el cambio es transparente para el paquete.

`id` se mantiene `bigint`: es append-only, igual que `doctor_contact_events`.

Acciones que **deben** registrarse desde el primer día:

```
doctor.created      doctor.updated       doctor.published    doctor.unpublished
doctor.verified     doctor.suspended     doctor.merged
claim.submitted     claim.approved       claim.rejected
user.suspended      role.assigned
import.applied      import.row_resolved  suppression.created
```

Registrados además desde el backoffice (Etapas 1–3):
- `user.reactivated`, `role.removed` y `slug.updated` (con los slugs anterior y nuevo en `properties`).
- `doctor.suspension_lifted` y `doctor.verification_rejected` (con el motivo).
- `location.updated`, `catalog.activated` y `catalog.deactivated`.
- Los cambios del agregado (especialidades, ubicaciones, contactos, idiomas) se registran como `doctor.updated`, con `part` y `op` en `properties`.

El historial es lo único de este documento que no se puede añadir después. Media jornada de trabajo ahora, irrecuperable más tarde.

---

# 15. Orden de migraciones

```
000  extensions + domain ulid + es_unaccent + immutable_unaccent
001  users
002  roles / permissions / role_has_permissions / model_has_roles / model_has_permissions  (spatie)
003  countries
004  regions
005  cities
006  city_aliases
007  specialties
008  specialty_aliases
009  languages
010  doctors                       (sin FK a import_batches)
011  doctor_profiles
012  doctor_specialties
013  doctor_languages
014  locations
015  doctor_locations
016  doctor_contacts
017  doctor_external_references
018  doctor_claims
019  import_batches                (+ ALTER TABLE doctors ADD FK import_batch_id)
020  import_rows
021  doctor_suppressions
022  slug_redirects
023  doctor_contact_events
024  activity_log                  (spatie)
```

La 000 es imprescindible antes que todo: `doctors.search_vector` referencia `es_unaccent`, y todos los PK usan el dominio `ulid`.

La FK `doctors.import_batch_id` se añade en la 019 porque `doctors` se crea antes que `import_batches`. Es la única dependencia circular del esquema y se resuelve con un `ALTER TABLE`.

> En Laravel, usa `DB::statement()` para extensiones, dominio, configuración de búsqueda, índices parciales, índices GIN, columnas generadas y `CHECK`. El `Schema` builder cubre el resto.

---

# 16. Seeds de Fase 1

## 16.1 Roles

```
admin    Administrador de MeeMedico
doctor   Usuario médico
```

`super_admin` no se crea todavía. El paquete de permisos permite añadirlo sin cambios de esquema.

## 16.2 Permisos

```
backoffice.access

doctors.view         doctors.create        doctors.update
doctors.verify       doctors.publish       doctors.suspend      doctors.merge

profiles.update

specialties.view     specialties.create    specialties.update   specialties.delete
locations.view       locations.create      locations.update     locations.delete
geography.manage

contacts.view        contacts.update

claims.view          claims.approve        claims.reject

imports.view         imports.create        imports.resolve      imports.apply
suppressions.manage

users.view           users.create          users.update         users.suspend
roles.manage

activity.view
```

**ADMIN:** todos los anteriores.
**DOCTOR:** `profile.view`, `profile.claim`, `profile.update` (limitado a `doctors.user_id = auth()->id()` y al subconjunto de campos del §10.2).

**`*.delete` no borra.** Ninguna entidad del directorio se borra físicamente (§3.5): `specialties.delete` y `locations.delete` autorizan la **baja lógica** (`status = 'inactive'`). Las Policies devuelven siempre `false` para `delete`, salvo en los alias de ciudad y especialidad, que son mapeos sin `status` y se corrigen borrándolos.

**Convención de nombres.** En plural (`profiles.update`), el permiso opera sobre cualquier registro y es del backoffice. En singular (`profile.update`), opera solo sobre lo propio, y el alcance lo limita una Policy.

La **lista canónica** es `database/seeders/PermissionSeeder.php`. Lo de arriba es una referencia y, si diverge, gana el seeder. Los documentos conceptuales v1.0 tenían dos listas divergentes (una con `doctors.delete`, otra con `specialties.manage` agregado); quedan reemplazadas.

## 16.3 Catálogos

- **Países:** Costa Rica (`+506`), Guatemala (`+502`), República Dominicana (`+1809`), Venezuela (`+58`). Todos en `status = 'inactive'` hasta que su carga esté lista.
- **Regiones y ciudades:** por país, antes del primer import de ese país.
- **Especialidades:** catálogo curado manualmente. No lo generes desde el archivo de import; el catálogo debe ser la autoridad y los alias el puente.
- **Idiomas:** español, inglés, francés, portugués.

---

# 17. Tablas de infraestructura de Laravel

No están contadas entre las 27, pero existen y forman parte del esquema:

```
migrations              password_reset_tokens      sessions
jobs                    job_batches                failed_jobs
cache                   cache_locks                personal_access_tokens
```

`password_reset_tokens` es requisito del "recuperar contraseña" ya incluido en el alcance. `personal_access_tokens` (Sanctum) no se usa en Fase 1 pero es la base de la API que consumirá la app React Native en V2.

---

# 18. Fuera de alcance de Fase 1

No se crean todavía:

```
patients      clinics        assistants
appointments  availability   schedules
reviews       ratings
plans         subscriptions  payments
conditions    procedures     services
checkins      calendar_integrations
seo_pages     seo_metadata
```

Y las decisiones que las mantienen desacopladas:

- **Membership:** no hay `is_premium` ni `subscription_type` en `doctors`. La futura cadena será `Doctor → Subscription → Plan → Features`, y el punto de enganche de la visibilidad ya existe en `doctor_contacts.is_public`.
- **Reviews:** no hay `rating` ni `reviews_count` en `doctors`. La reputación se calculará desde `reviews` y se cacheará donde convenga.
- **Booking:** `locations` y `doctors` ya tienen la estructura para colgar disponibilidad. Entonces habrá que añadir `countries.timezone` o `locations.timezone`.
- **Clinics:** una ubicación puede ser `location_type = 'clinic'` sin que `Clinic` sea una entidad. La evolución será `Doctor → Clinic → Location`, y la N:N actual de `doctor_locations` ya la admite.
- **Búsqueda avanzada:** `search_vector` y los índices trigram cubren Fase 1. Fase 2 añade una vista materializada o Meilisearch detrás de una interfaz `DoctorSearchService`, sin tocar el núcleo.

---

# 19. Checklist antes de dar Fase 1 por cerrada

**Esquema**
- [ ] Extensiones, dominio `ulid` y `es_unaccent` creados en la migración 000
- [ ] Todas las FK son `RESTRICT` salvo las del agregado propio
- [ ] Los seis índices únicos parciales existen y están probados con un test que intente violarlos
- [ ] Ningún `ENUM` nativo de PostgreSQL en el esquema
- [ ] Ningún índice sobre `(latitude, longitude)`

**Dominio**
- [ ] Las ocho Actions del §11 existen y tienen test
- [ ] Ninguna regla de negocio dentro de un controlador o recurso de Filament
- [ ] `PublishDoctorAction` rechaza fichas sin especialidad, ubicación o contacto público
- [ ] Las fichas incompletas se sirven con `noindex`

**Importación**
- [ ] Perfilado del archivo real hecho (§12.5)
- [ ] Los cinco comandos corren en cola y son reejecutables
- [ ] `import:match` consulta `doctor_suppressions` antes que nada
- [ ] Ningún merge automático por nombre
- [ ] Tasa de duplicados medida y reportada por lote, objetivo por debajo del 2%

**Operación**
- [ ] `activity_log` activo con las acciones del §14.4
- [ ] Purga de CDN al publicar, actualizar y fusionar
- [ ] Sentry configurado
- [ ] Backups con PITR verificados con una restauración real

---

# 20. Decisiones que esta especificación fija

1. `User` y `Doctor` son entidades distintas; un Doctor existe sin User.
2. Creator, manager, verificador y reclamante son cuatro relaciones diferentes.
3. El país es un atributo obligatorio del Doctor, no una consecuencia de su ubicación.
4. Claim, verificación y publicación son tres estados ortogonales.
5. El importador nunca escribe directamente en `doctors`: pasa por staging.
6. Solo se fusiona con evidencia fuerte; la ambigüedad va a revisión humana.
7. Ninguna ficha se publica sin especialidad, ubicación y contacto público.
8. Ningún slug cambia sin dejar un 301.
9. Ninguna ficha suprimida vuelve a crearse.
10. Membership, reviews y booking extienden el núcleo; no lo modifican.
