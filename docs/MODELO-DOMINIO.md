# Modelo de Dominio — Directorio Médico

**Producto:** MeeMedico.com
**Fase:** 1
**Versión:** 2.0
**Estado:** Vigente
**Esquema:** `docs/DATABASE.md`

---

# 0. Qué contiene este documento y qué no

Este documento explica **qué entidades existen, qué significan y por qué el modelo es como es**.

No contiene nombres de tabla, listas de campos ni SQL. Todo eso vive en `DATABASE.md`, que es la única fuente de verdad del esquema. La separación es deliberada: en la versión 1.0 la información estaba duplicada en varios documentos y divergió en menos de un mes.

| Fuente | Posee |
|---|---|
| Este documento | Conceptos, relaciones, estados, invariantes, razonamiento |
| `DATABASE.md` | Esquema, tipos, índices, restricciones, migraciones |
| `PermissionSeeder.php` | Lista canónica de permisos |

---

# 1. Principios del modelo

1. **El modelo representa la realidad del dominio médico, no las pantallas.** Si una pantalla necesita un dato que el dominio no tiene, se discute el dominio, no se añade un campo.
2. **Ningún atributo "por si acaso".** Todo campo debe justificar su utilidad en la fase actual.
3. **Lo que no pertenece a esta fase no se modela.** Se deja constancia de cómo se enganchará más tarde.
4. **Las entidades del directorio no se borran.** Se cambian de estado.
5. **Una decisión que pueda bloquear una funcionalidad futura se señala por escrito**, aunque se tome igualmente.

---

# 2. Las entidades

## 2.1 Doctor

**Es** la entidad profesional del directorio: la persona que el paciente busca, compara y a la que llama.

**No es** una cuenta de usuario. Un Doctor existe sin que nadie haya iniciado sesión nunca, y la inmensa mayoría de los del directorio estarán así durante meses. Esta separación es el eje del modelo y está desarrollada en `modelo-identidad.md`.

Un Doctor pertenece siempre a **un país**, y el país es un atributo propio, no algo que se deduzca de dónde tiene consulta. Tres razones:

- Un médico en borrador puede no tener aún ninguna ubicación, y aun así tiene que pertenecer a algún sitio.
- La URL pública lo exige: `/{pais}/medicos/{slug}`.
- La unicidad de su número de colegiado solo tiene sentido dentro de un país, porque cada colegio médico numera por su cuenta.

**Caso límite aceptado:** un médico con consulta en dos países se modela con el país principal y ubicaciones en ambos. Su ficha vive en una sola URL de país. La ubicación extranjera aparece en la ficha pero no genera listado en el otro país.

## 2.2 DoctorProfile

El contenido editorial de la ficha: titular, biografía, formación, experiencia, fotografía.

Se separa del Doctor para mantener estrecha la entidad que se consulta en todos los listados. **Condición de la separación:** el perfil se crea siempre junto al Doctor, en la misma transacción. Si eso no se garantiza, la separación deja de compensar y hay que fusionarlos.

**Deuda aceptada:** formación y experiencia son texto libre. Cuando los perfiles avanzados quieran entradas estructuradas (título, institución, año), habrá que parsear texto. Estructurarlo hoy significaría pedirle al importador datos que las fuentes no traen.

## 2.3 Specialty

Catálogo **global**, compartido por los cuatro países. Cardiología es cardiología en todos los mercados, y un catálogo por país multiplicaría el mantenimiento y rompería las páginas transversales.

Admite **jerarquía de dos niveles** (Cardiología → Cardiología intervencionista). La jerarquía existe desde Fase 1 porque condiciona la taxonomía de URLs, que es la decisión más cara de revertir de todo el proyecto.

Un Doctor tiene una o varias especialidades, y **como máximo una principal**. La principal es la que encabeza la ficha y la que se usa cuando hay que elegir una sola.

**Las variantes de nombre no son especialidades.** "CARDIOLOGIA CLINICA" en un archivo de importación, "cardiólogo" como término de búsqueda y una denominación local distinta son **alias** que apuntan a la misma especialidad. El catálogo es la autoridad; los alias son el puente hacia el mundo real.

## 2.4 Location

Una dirección física donde se atiende.

Es una entidad **compartible**: la misma torre médica puede alojar a veinte profesionales. De ahí que la relación con Doctor sea de muchos a muchos, y no una dirección por médico.

Esa decisión tiene un coste que hay que gestionar: un médico podría editar una dirección que comparte sin saberlo. **Regla operativa de Fase 1:** una ubicación asociada a más de un médico solo la puede editar un administrador. Cuando llegue la entidad Clínica, la dirección pasará a pertenecerle y la regla desaparece.

Un Doctor tiene **como máximo una ubicación principal**, que es la que determina en qué listados de ciudad aparece.

Las coordenadas son opcionales. En Fase 1 no hay mapa; el modelo las guarda porque el importador a veces las trae y recuperarlas después sale caro.

## 2.5 Contact

Teléfono, móvil, WhatsApp, correo o sitio web de un médico.

Es una entidad de primer nivel, no un atributo, por tres motivos:

1. **En Fase 1 el paciente ve la ficha y llama.** Sin contacto, la ficha no resuelve nada y el directorio no tiene propuesta de valor.
2. Un médico con dos consultorios tiene dos teléfonos distintos, así que un contacto puede pertenecer a una ubicación concreta o ser general.
3. Cada contacto lleva una marca de **visibilidad pública**, que es el punto exacto donde se enganchará la futura capa de membresías sin tocar nada más.

**Nota de producto.** El MVP original planteaba que el contacto solo fuera visible para pacientes registrados o para médicos con suscripción. En Fase 1 no existe ninguna de las dos cosas, así que será público. Ocultar más adelante algo que ya era público es una regresión visible para el usuario y para el buscador. La recomendación es mantenerlo público de forma permanente en el plan gratuito y monetizar visibilidad, posicionamiento y productividad, nunca el teléfono.

## 2.6 Geografía: País, Región, Ciudad

Jerarquía estricta de tres niveles. Cada ubicación cuelga de una ciudad, cada ciudad de una región, cada región de un país.

La consistencia de la cadena está garantizada por el motor de base de datos, no por la aplicación: es imposible registrar una ubicación cuya ciudad no pertenezca a su región.

**El caso que obligó a rediseñar esto:** en Costa Rica hay más de un "San José" en provincias distintas. Como el nombre de la ciudad viaja en la URL dentro del ámbito del país, dos ciudades legítimas producirían la misma dirección web y una de las dos páginas sería inalcanzable. El modelo obliga a desambiguar en el momento de la carga, no en producción.

Las ciudades, igual que las especialidades, tienen **alias** para absorber el texto libre de las fuentes de importación.

## 2.7 Language

Catálogo pequeño. Un médico habla uno o varios idiomas.

Es una relación, no un atributo, porque el idioma es un filtro declarado del producto y filtrar por un valor guardado dentro de una estructura anidada es el peor de los escenarios.

## 2.8 ExternalReference

El identificador que un Doctor tiene en una fuente externa: el registro del colegio médico, un padrón, un agregador.

Un mismo Doctor puede tener **varias**. No es un atributo por una razón concreta: cuando se fusionan dos fichas duplicadas, la superviviente tiene que heredar las referencias de ambas. Si no, la siguiente importación no reconoce la referencia huérfana y recrea el duplicado que se acababa de eliminar.

Cada referencia guarda cuándo se vio por primera y por última vez. Dejar de aparecer en la fuente es la señal para revisar una baja: médico retirado, trasladado o fallecido.

## 2.9 ImportBatch e ImportRow

Un lote de importación y cada una de sus filas.

Existen porque **el importador nunca escribe directamente en el directorio**. Cada fila pasa por una zona intermedia donde se normaliza, se intenta emparejar con un médico existente y, si hay ambigüedad, espera a que una persona decida.

La fila conserva el **dato original intacto**, lo cual permite reprocesar un lote completo cuando se descubra que el mapeo de ciudades estaba mal, sin volver a pedir el archivo. Y guarda a qué médico dio lugar, lo que permite revertir un lote concreto.

## 2.10 Suppression

El registro de que una persona pidió no aparecer.

Existe porque vamos a publicar fichas de profesionales que nunca dieron su consentimiento, y porque marcar una ficha como inactiva no basta: la siguiente importación la recrearía. El importador consulta esta lista antes de crear cualquier ficha, y ninguna ficha que coincida por colegiado, teléfono o correo puede publicarse mientras la supresión esté vigente.

**Es reversible, pero no se borra.** Un médico que pidió salir puede ver que el directorio le conviene y querer volver. Entonces la supresión se **revoca**: queda registrado cuándo lo pidió, por qué canal, cómo se verificó que era la misma persona y quién lo registró. A partir de ahí deja de bloquear, pero su ficha no reaparece sola: un administrador la publica, pasando por la misma puerta de calidad que cualquier otra. Si más adelante vuelve a pedir salir, se registra una supresión nueva. El historial completo queda como prueba ante una reclamación.

La verificación de identidad es lo delicado: una revocación falsa publicaría a alguien que pidió no aparecer. Por eso el motivo es obligatorio y debe decir cómo se comprobó la identidad, por ejemplo con una llamada al teléfono que ya figuraba en la ficha.

### Canal de solicitud — pendiente para la fase de frontend

Hoy la solicitud llega por canales informales y un administrador la registra a mano. Para el lanzamiento del sitio público:

- **En la ficha pública**, un enlace estático «¿Eres este médico? Solicita la baja o corrige tus datos». Es HTML fijo, así que la ficha sigue siendo cacheable entera en CDN.
- **WhatsApp:** un enlace `wa.me` al número de operación del país, con un mensaje prellenado que incluye el nombre y la URL de la ficha. El número vive en configuración, no en la base.
- **Formulario web:** una isla de React que envía a `POST /api/v1/suppression-requests`, sin sesión, con límite de peticiones y protección anti-bot. Pide nombre, colegiado, teléfono o correo de contacto y mensaje, y **envía un correo al buzón de operación del país**. No crea la supresión ni despublica nada: el administrador verifica la identidad y la registra en el backoffice.
- **El mismo canal sirve para pedir volver** (revocación).
- **Cuando exista el claim:** si una persona con supresión vigente reclama su ficha, primero se revoca la supresión.

**Riesgo conocido:** con el correo como única cola, no queda traza en el sistema de cuándo llegó cada solicitud ni de si se respondió a tiempo. Si el volumen crece o la asesoría legal de cada país exige acreditar plazos de respuesta, la solución es una tabla `suppression_requests` que guarde la solicitud recibida antes de que un humano la resuelva. Los plazos legales concretos hay que validarlos con asesoría local (Ley 8968 en Costa Rica, Ley 172-13 en República Dominicana).

## 2.11 SlugRedirect

La memoria de las direcciones web antiguas.

En un producto cuyo canal de adquisición es el buscador, cambiar una dirección ya indexada sin dejar una redirección permanente destruye posicionamiento acumulado. Y en un directorio poblado por carga masiva se corrigen nombres mal escritos durante meses.

---

# 3. Relaciones

```
Country 1 ── N Region 1 ── N City 1 ── N Location
                                            │
                                            │ N:N
                                            │
Country 1 ── N Doctor ─── 1:1 ── DoctorProfile
                 │
                 ├── N:N ── Specialty        (una principal como máximo)
                 ├── N:N ── Language
                 ├── N:N ── Location         (una principal como máximo)
                 ├── 1:N ── Contact          (una principal por tipo)
                 ├── 1:N ── ExternalReference
                 └── 0..1 ── merged_into ──> Doctor
```

---

# 4. Ciclo de vida del Doctor: tres ejes ortogonales

Este es el punto que más confusión generó en la versión 1.0. Un Doctor tiene **tres estados independientes** que no se implican entre sí.

| Eje | Responde a | Lo decide |
|---|---|---|
| **Publicación** | ¿Es visible en el sitio? | MeeMedico |
| **Verificación** | ¿Confirmamos que es quien dice ser? | MeeMedico, contra el registro oficial |
| **Reclamación** | ¿Hay un usuario administrando esta ficha? | El propio médico, con aprobación |

Las combinaciones que ocurren en la realidad:

- Publicado, sin verificar, sin reclamar → **el caso mayoritario** tras la carga masiva de un agregador.
- Publicado, verificado, sin reclamar → carga masiva desde el registro oficial del colegio médico.
- Publicado, verificado, reclamado → el médico activo, objetivo del producto.
- Borrador, sin verificar, sin reclamar → ficha incompleta que no pasa la puerta de calidad.

**Tener número de colegiado registrado no es estar verificado.** Un número copiado de un agregador puede estar mal, desactualizado o pertenecer a otro. Verificado significa que MeeMedico lo contrastó contra el registro oficial.

**En la interfaz:** se muestra insignia solo a los verificados. Para el resto, nada. No se marca negativamente a fichas de personas que no pidieron aparecer.

---

# 5. Invariantes del dominio

Reglas que el modelo garantiza siempre. Las que la base de datos no puede imponer viven en servicios de dominio, no en controladores, porque los comandos del importador tienen que respetarlas igual que el sitio web.

1. Un Doctor pertenece exactamente a un país.
2. Un Doctor tiene como máximo una especialidad principal, una ubicación principal y un contacto principal por tipo.
3. **No se publica un Doctor sin al menos una especialidad, una ubicación con ciudad y un contacto público.** Miles de fichas vacías indexadas degradan el posicionamiento del sitio entero: posicionan peor que no existir.
4. Una ficha incompleta nunca se sirve indexable.
5. Un slug no cambia sin dejar redirección permanente.
6. Una ficha suprimida no vuelve a crearse ni a publicarse mientras la supresión esté vigente.
7. Una ficha fusionada no se borra: queda apuntando a la superviviente, y su dirección web sigue resolviendo.
8. La cadena ciudad → región → país es siempre consistente.

---

# 6. La identidad del médico: el problema central del modelo

**No existe una clave natural fiable.** El número de colegiado está disponible en un porcentaje alto de los casos, pero no en todos, y ese hecho condiciona todo el diseño de la importación.

La jerarquía de señales, de más a menos fiable:

| Señal | Fuerza | Qué se hace |
|---|---|---|
| Referencia en la fuente externa | Fuerte | Actualizar la ficha existente |
| Colegiado + país | Fuerte | Actualizar la ficha existente |
| Teléfono o correo coincidente | Débil | Candidato, a revisión humana |
| Nombre parecido en la misma ciudad | Muy débil | Candidato, a revisión humana |

**El principio que gobierna todo lo anterior:**

> Un duplicado es un error barato. Una fusión incorrecta es un error caro e irreversible.

Dos fichas del mismo cardiólogo se ven, molestan, y un administrador las arregla en un minuto. Fusionar dos médicos distintos que se llaman igual mezcla especialidades, direcciones y teléfonos de dos profesionales reales: el paciente llama al número equivocado, el médico correcto pide que lo saquen, y reconstruir qué dato era de quién ya no es posible.

De ahí sale la política completa: **solo se fusiona automáticamente con evidencia fuerte; todo lo demás se duplica y se marca para revisión.** El modelo prefiere deliberadamente los falsos negativos.

**Consecuencia conocida:** la comparación de nombres fallará con nombres compuestos ("Juan Carlos Pérez Rodríguez" frente a "Juan Pérez Rodríguez") y generará duplicados. Es el lado correcto del error.

---

# 7. Fusión de duplicados

Cuando un administrador confirma que dos fichas son la misma persona, la superviviente absorbe especialidades, idiomas, ubicaciones, contactos, referencias externas y solicitudes de reclamación. La perdedora queda marcada como fusionada, apuntando a la superviviente, y su dirección web redirige de forma permanente.

**La ficha perdedora no se borra.** Es lo que impide que la siguiente importación la recree y lo que mantiene válida cualquier URL ya indexada.

**No existe operación inversa automática.** Deshacer una fusión errónea es trabajo manual con el registro de actividad en la mano. Por eso la fusión es siempre manual, con confirmación explícita, y el importador jamás la ejecuta.

---

# 8. Las URLs forman parte del dominio

En un producto SEO-first, la dirección web de una entidad no es un detalle de presentación: es parte de su identidad pública y su coste de cambio es alto.

```
/{pais}/medicos/{slug-del-medico}
/{pais}/{especialidad}/{ciudad}
```

El país aparece con su nombre completo (`costa-rica`), no con su código, porque la palabra tiene valor en las búsquedas reales.

Consecuencias en el modelo:

- El slug de un médico es único **dentro de su país**, no globalmente.
- El slug de una ciudad es único dentro de su país, no dentro de su región.
- Hay una lista de términos reservados que el generador de slugs nunca puede producir.
- Todo cambio de slug deja redirección.

---

# 9. Decisiones y su motivo

| Decisión | Motivo |
|---|---|
| El país es atributo propio del Doctor | Un borrador sin ubicación tiene que existir en algún sitio; y la URL y la licencia lo exigen |
| Catálogo de especialidades global con jerarquía | La taxonomía de URLs es lo más caro de revertir |
| Alias para ciudades y especialidades | El mapeo de texto libre a catálogo es el grueso del esfuerzo de importación; los alias hacen que cada lote cueste menos que el anterior |
| Contacto como entidad, no como atributo | Es la propuesta de valor de Fase 1 y el enganche de la futura membresía |
| Idioma como relación | Es un filtro del producto |
| Ubicación compartible | La torre médica es la realidad; la clínica llegará después a poseerla |
| Referencias externas múltiples | La fusión obliga a heredar las de ambas fichas |
| Zona intermedia de importación | Sin colegiado universal, la carga masiva necesita intervención humana, y la intervención necesita estado |
| Lista de supresión | Sin ella, la siguiente importación recrea la ficha retirada |
| Sin `rating`, `premium` ni `citas` en Doctor | Reputación, membresía y reservas extenderán el núcleo; no deben modificarlo |

---

# 10. Fuera del modelo, y cómo entrará

| Concepto | Fase | Punto de enganche ya previsto |
|---|---|---|
| Paciente | 2 | Entidad nueva; no toca Doctor |
| Reserva y disponibilidad | 3 | Cuelgan de Doctor y Location. Hará falta zona horaria |
| Review y reputación | 3 | Entidad nueva. Nunca un promedio dentro de Doctor |
| Membresía y pagos | 3 | Cadena Doctor → Suscripción → Plan. La visibilidad del contacto ya tiene su marca |
| Clínica | V2 | Una ubicación ya puede ser de tipo clínica sin que la entidad exista. La relación pasará a Doctor → Clínica → Ubicación |
| Asistente o secretaria | V2 | Ver el bloqueo conocido en `modelo-identidad.md` |
| Telemedicina, receta, historia clínica | V2+ | Sin enganche todavía. No modelar |

---

# 11. Puntos abiertos

| # | Cuestión | Por qué importa |
|---|---|---|
| 1 | ¿Las subespecialidades tendrán página propia o serán solo un filtro dentro de la especialidad padre? | Define la estructura de URLs y el sitemap. Decidir antes de generar el primer sitemap |
| 2 | ¿De qué fuente sale el archivo de carga inicial de cada país? | Determina cuántas fichas nacen verificadas y cuánta revisión manual habrá |
| 3 | ¿Qué umbral de parecido de nombres dispara la revisión manual? | Se fija con datos reales tras el perfilado del archivo |
