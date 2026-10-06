# AGENTS.md — MeeMedico

Instrucciones para agentes de código. Documentación completa en `docs/`.

## Qué es

Directorio médico multipaís de LATAM (Costa Rica, Guatemala, República Dominicana, Venezuela).
Producto SEO-first y mobile-first. Fase 1 = directorio + identidad + carga masiva + claim.
El paciente busca, ve la ficha y llama. **No hay reservas, pacientes, reviews ni pagos todavía.**

## Stack

PHP 8.4 · Laravel 13 · PostgreSQL 17 · Blade + islas de React (TypeScript) · Filament 5 · Redis + Horizon · Tailwind · Pest

Una sola aplicación, un repositorio, un despliegue. `meemedico.com` público y `admin.meemedico.com` (Filament) servidos por la misma app.

## Estructura

```
app/Models/       todos los modelos Eloquent (con HasUppercaseUlids)
app/Domain/       Directory, Identity, Claim, Import, Geo, Search  ← las reglas viven aquí (Actions, Enums)
app/Http/         Controllers/{Public,Doctor,Api/V1}, Middleware, Resources
app/Filament/     backoffice
resources/views/  Blade: public/, doctor/, components/
resources/js/     islands/, dashboard/, claim/, lib/
docs/             ARQUITECTURA.md, DATABASE.md, modelo-dominio.md, modelo-identidad.md
```

---

## Reglas que no se negocian

Estas son las que un agente rompe sin darse cuenta. Si una propuesta las viola, dilo antes de escribir código.

### 1. La lógica de negocio vive en Actions, nunca en controladores ni en recursos de Filament

`PublishDoctorAction`, `ApproveClaimAction`, `MergeDoctorsAction`, `CreateDoctorAction`, `UpdateSlugAction`, `ApplyImportBatchAction`.

Motivo: el sitio web, Filament, los comandos del importador y la futura API móvil deben ejecutar la misma regla. Si está en un controlador, el comando de consola la salta.

### 2. Ninguna página pública puede depender de sesión

Las páginas públicas (ficha, listado, home, búsqueda, especialidad) son **Blade puro, cacheables enteras en CDN**.

- Prohibido Livewire en páginas públicas. Requiere sesión y CSRF, y eso las vuelve no cacheables.
- Prohibido convertir la ficha del médico en una SPA de React.
- La interactividad va en islas de React que consumen `/api/v1/*`.
- Presupuesto: **menos de 100 KB de JS comprimido** en páginas públicas. El CI falla si se supera.

### 3. Invariantes de publicación

- No se publica un médico sin **≥1 especialidad, ≥1 ubicación con ciudad y ≥1 contacto público**.
- Las fichas incompletas se sirven con `noindex`.
- Nunca se cambia un slug sin escribir antes en `slug_redirects` (301, jamás 404).
- Al publicar, actualizar o fusionar un médico, se purga la caché de CDN.

### 4. Reglas del importador

- **Nunca escribe directamente en `doctors`.** Pasa por `import_rows` (staging).
- **Nunca crea `Users`.** La carga masiva crea médicos sin cuenta.
- **Nunca fusiona automáticamente por nombre.** Solo con evidencia fuerte (referencia externa o licencia + país). Lo ambiguo va a revisión humana.
- Consulta `doctor_suppressions` antes de crear cualquier ficha.
- `import:apply` nunca publica. Publicar es un comando aparte.
- Un duplicado es un error barato. Una fusión incorrecta es irreversible. Ante la duda, duplica.

### 5. Convenciones de base de datos

- Identificadores ULID mediante el dominio `ulid` (`varchar(26) COLLATE "C"`).
- Fechas siempre `timestamptz`, nunca `timestamp`.
- **Sin tipos `ENUM` de PostgreSQL.** `varchar` + `CHECK` + `enum` de PHP con cast de Eloquent.
- FK con `ON DELETE RESTRICT` por defecto. `CASCADE` solo dentro del propio agregado.
- Ninguna entidad del directorio se borra físicamente. Se usa `status`.
- Índices únicos parciales (`WHERE is_primary`, `WHERE status = 'approved'`) en lugar de trucos con columnas generadas.
- No se añaden campos "por si acaso". Todo campo necesita justificación en la fase actual.

### 6. Sin patrón Repository sobre Eloquent

Eloquent ya es la capa de acceso a datos. Añadirlo solo introduce ceremonia.

### 7. Disciplina de fases

No implementes funcionalidad de fases futuras aunque sea técnicamente posible: telemedicina, historia clínica, CRM, IA diagnóstica, marketplace farmacéutico, app nativa, reservas, disponibilidad, reviews, suscripciones, pacientes, cuentas de clínica, planes de seguro, asistentes.

Establecimientos (clínicas, hospitales) y aseguradoras **sí** son Fase 1, pero solo como datos del backoffice. Su alcance exacto está en `docs/MODULO-ESTABLECIMIENTOS-SEGUROS.md` §2.

Si una idea pertenece a una fase posterior, dilo y explica cómo dejar la arquitectura preparada sin implementarla.

---

## Comandos

```bash
# desarrollo
php artisan serve
npm run dev
php artisan horizon

# backoffice (Filament en http://admin.localhost:8000, ADMIN_DOMAIN en .env)
php artisan user:create-admin

# plantilla Excel de carga masiva (una por país; formato canónico de entrada)
php artisan import:template CR       # → storage/app/import-templates/
php artisan import:sample CR         # plantilla llena con médicos SINTÉTICOS para probar

# el backoffice encola las etapas: en local hace falta un worker
php artisan queue:work               # o `php artisan dev` (servidor + cola + vite)

# pipeline de importación (en este orden, cada uno reejecutable)
php artisan import:ingest    {archivo} --country=CR --source=colegio_medicos_cr
php artisan import:normalize {batch}
php artisan import:match     {batch}
php artisan import:apply     {batch}
php artisan import:publish   {batch}

# calidad
./vendor/bin/pest
./vendor/bin/pint
npm run build        # falla si se supera el presupuesto de JS
```

---

## Al escribir código

**Tests:** el foco está en `tests/Domain/` (Actions e invariantes) y en el pipeline de importación, no en los controladores.

- Corren contra **PostgreSQL**, nunca SQLite: el esquema depende de dominios, `CHECK`, índices parciales y `pg_trgm`.
- Usan la base `meemedico_staging`, que **se borra en cada ejecución** (`RefreshDatabase`). No guardes en ella datos que quieras conservar.
- En paralelo: `php artisan test --parallel --drop-databases`. Sin `--drop-databases` quedan bases `meemedico_staging_test_N` huérfanas.
- Los tests de Filament usan `set()` y `mountAction()`, no `fillForm()`: en Filament 5, `fillForm()` no dispara `afterStateUpdated`.

**Permisos:** la lista canónica está en `database/seeders/PermissionSeeder.php`. Los documentos la referencian, no la copian. Si añades un permiso, va ahí primero.

**Endpoints de API:** son API Resources bajo `/api/v1`, con Sanctum, llamando a las mismas Actions que los controladores web. Son el primer borrador de la API que consumirá la app React Native de V2.

**Multipaís:** el país es una dimensión de los datos (`country_id`), nunca un límite de infraestructura. URLs `/{pais}/medicos/{slug}` y `/{pais}/{especialidad}/{ciudad}`, con el nombre completo del país (`costa-rica`), no el código ISO.

**Slugs reservados:** `medicos`, `especialidades`, `clinicas`, `admin`, `api`, `assets` y los slugs de país. El generador de slugs de médico nunca puede producirlos.

---

## Al proponer decisiones

Este proyecto prioriza análisis crítico sobre confirmación.

- Si una propuesta tiene una debilidad, señálala antes de implementarla.
- Cuando existan varias alternativas válidas, compáralas antes de recomendar.
- Señala explícitamente los supuestos que estés haciendo.
- Si una decisión previa puede generar un problema futuro, indícalo aunque contradiga lo ya acordado.
- Cuando falte información importante, pregúntala antes de cerrar la solución.

Para decisiones de peso, estructura la respuesta así: contexto, opciones, ventajas y desventajas, impacto en MeeMedico, recomendación, impacto en fases futuras.

**Criterio de fondo:** ¿esto resuelve correctamente el problema de hoy sin crear un problema innecesario para MeeMedico mañana?

---

## Checklist antes de dar algo por terminado

- [ ] La regla de negocio está en una Action, no en un controlador
- [ ] La página pública no depende de sesión y sigue siendo cacheable
- [ ] El presupuesto de JS se mantiene por debajo de 100 KB
- [ ] Hay test en `tests/Domain/` para la invariante nueva
- [ ] Si cambió el esquema, `docs/DATABASE.md` cambia en el mismo PR
- [ ] Si cambió un permiso, `PermissionSeeder` cambia en el mismo PR
- [ ] Ninguna funcionalidad de fase futura se coló

---

## Documentación

| Archivo | Contenido |
|---|---|
| `docs/ARQUITECTURA.md` | Decisiones de stack, alternativas descartadas, infraestructura, riesgos |
| `docs/DATABASE.md` | 33 tablas, índices, restricciones, orden de migraciones, pipeline de import |
| `docs/MODULO-ESTABLECIMIENTOS-SEGUROS.md` | Establecimientos y aseguradoras: alcance, referencias de otras plataformas, decisiones, pantallas, plan por etapas |
| `docs/MODELO-DOMINIO.md` | Conceptos del dominio médico |
| `docs/MODELO-IDENTIDAD.md` | Actores, roles, reglas de autorización |

Lee `docs/DATABASE.md` antes de tocar cualquier migración o modelo.
