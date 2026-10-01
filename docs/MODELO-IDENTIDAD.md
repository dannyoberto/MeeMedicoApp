# Modelo de Identidad y Administración

**Producto:** MeeMedico.com
**Fase:** 1
**Versión:** 2.0 (reemplaza la 1.0)
**Estado:** Vigente
**Esquema:** `docs/DATABASE.md` · **Permisos:** `database/seeders/PermissionSeeder.php`

---

# 0. Qué contiene este documento y qué no

Explica **quién puede hacer qué en MeeMedico y por qué**.

No contiene nombres de tabla, listas de campos ni el catálogo de permisos. El catálogo vive en el seeder, que es código y no puede divergir de la realidad. La versión 1.0 de este documento llegó a contener **dos listas de permisos distintas y contradictorias separadas por veinte líneas**; la regla de propiedad existe para que eso no vuelva a pasar.

---

# 1. El principio fundacional: User no es Doctor

**User** representa identidad y autenticación: alguien que puede iniciar sesión.
**Doctor** representa la entidad profesional del directorio.

Son dominios separados. La consecuencia práctica:

> Un Doctor puede existir sin User, y lo normal es que así sea.

Tras la carga masiva habrá miles de médicos publicados de los que nadie ha iniciado sesión jamás. Esa es la situación por defecto del producto, no un caso raro.

**Por qué importa tanto.** La alternativa (que todo médico sea un usuario) obligaría a crear cuentas falsas durante la importación, a inventar contraseñas, a gestionar el consentimiento de gente que no pidió una cuenta, y a mezclar el ciclo de vida de una credencial con el de una ficha pública. Cada uno de esos problemas es peor que la relación opcional que tenemos.

**Regla derivada, aplicable al importador:** la carga masiva **nunca crea usuarios**. Crea fichas.

---

# 2. Las cuatro relaciones entre un User y un Doctor

Son distintas y no deben confundirse. Este es el error de modelado más común en productos de directorio.

| Relación | Significa | Cuándo se fija | Cambia |
|---|---|---|---|
| **Creador** | Quién dio de alta la ficha | En la creación | Nunca |
| **Administrador** | Quién gestiona la ficha hoy | Al aprobarse una reclamación | Sí |
| **Verificador** | Qué administrador confirmó la identidad profesional | Al verificar | Con cada reverificación |
| **Solicitante** | Quién pidió hacerse cargo de la ficha | Al enviar la solicitud | Puede haber varios |

Un administrador de MeeMedico que crea una ficha es su **creador**, no su administrador ni su dueño. El médico que después la reclama pasa a ser su **administrador**, pero no fue su creador. Y mientras tanto pudo haber tres **solicitantes** compitiendo por la misma ficha.

---

# 3. Escenarios canónicos

**A. Médico creado por importación, nunca reclamado.**
Ficha publicada, sin usuario asociado. El caso mayoritario. El paciente la ve y llama.

**B. Médico creado por importación y reclamado después.**
La ficha existía antes de que su titular se registrara. Al aprobarse la reclamación, la ficha gana administrador sin cambiar de identidad ni de dirección web.

**C. Usuario registrado sin ficha asociada.**
Alguien se registró pero su solicitud aún no fue aprobada, o fue rechazada. Tiene cuenta y no tiene directorio.

**D. Administrador de MeeMedico.**
Usuario con rol administrativo y sin ficha propia. Opera la plataforma.

**E. Administrador que además es médico.**
Un usuario puede acumular ambos roles. Nada en el modelo lo impide, y conviene que así sea para las pruebas.

---

# 4. Actores

## Fase 1

**ADMIN — Administrador de MeeMedico.**
Opera la plataforma: gestiona el directorio, verifica profesionales, resuelve reclamaciones, ejecuta y revisa importaciones, administra catálogos y geografía, y gestiona cuentas.

**DOCTOR — Usuario médico.**
Reclama su ficha y, una vez aprobado, la mantiene dentro de un alcance acotado (§6).

## Fases futuras, no modelados

**ASISTENTE / SECRETARIA (V2).** La mayoría de los médicos delegan la gestión de agenda. Es el actor futuro más probable y el que provoca el bloqueo conocido del §9.

**CLÍNICA (V2).** Gestiona varios profesionales y varias sedes.

**PACIENTE (Fase 2).** Hoy el paciente es un visitante anónimo. No tiene cuenta, no necesita una, y añadirla ahora no aportaría nada: no hay nada que reservar.

---

# 5. Roles y permisos: el modelo

Tres decisiones estructurales:

1. **El rol está separado del usuario.** Un usuario puede tener varios roles y los roles se asignan y se retiran sin tocar la cuenta.
2. **El permiso está separado del rol.** Los permisos son la unidad de autorización; los roles son agrupaciones convenientes. Añadir una capacidad nueva no obliga a crear un rol nuevo.
3. **La autorización se comprueba siempre por permiso, nunca por nombre de rol.** Preguntar "¿es administrador?" en el código convierte cualquier cambio futuro de roles en una cacería por todo el repositorio. Se pregunta "¿puede aprobar reclamaciones?".

**Implementación.** Se usa un paquete de permisos del ecosistema en lugar de tablas propias. El motivo es operativo: la comprobación de autorización ocurre en cada petición y necesita caché, y el paquete la trae integrada junto con la conexión al panel de administración. Los tres principios de arriba se cumplen igual; lo que cambia son los nombres de las tablas.

**El catálogo de permisos vive en el seeder.** Este documento no lo reproduce.

---

# 6. El alcance editable tras la reclamación

**Esta es la regla de autorización más importante del producto.**

Aprobar una reclamación **no** convierte al médico en dueño absoluto de su ficha.

| Puede editar | No puede editar |
|---|---|
| Titular, biografía, fotografía | Nombre y apellidos |
| Sus datos de contacto | Número de colegiado |
| Sus ubicaciones | Estado de verificación |
| | Sus especialidades |
| | El estado de publicación |

**Por qué.** Si un médico verificado pudiera cambiarse el nombre y el número de colegiado conservando la insignia, la verificación no significaría nada: sería un sello transferible. Lo mismo con las especialidades, que determinan en qué listados aparece y son por tanto un activo competitivo.

Los cambios en los campos bloqueados se solicitan y los aplica un administrador.

---

# 7. Reclamación de perfil

## 7.1 Qué es

El mecanismo por el que un profesional toma el control de una ficha que MeeMedico creó sin él. Es, además, **el motor de adquisición de médicos del producto** y el mecanismo por el que sube el porcentaje de fichas verificadas sin trabajo manual proporcional.

## 7.2 La solicitud es una entidad, no un estado

En la versión 1.0, la reclamación era solo un estado de la ficha. Ese diseño no podía representar **quién** había solicitado, ni dos solicitantes compitiendo por un homónimo, ni el historial de rechazos.

Hoy la solicitud es una entidad propia con solicitante, evidencia, revisor y resolución. El estado que cuelga de la ficha es una **copia cacheada** de la última resolución, mantenida por el servicio que aprueba o rechaza. La fuente de verdad es la solicitud.

Consecuencias:

- Pueden coexistir **varias solicitudes pendientes de usuarios distintos** sobre la misma ficha. Es el caso real de los homónimos y lo resuelve un administrador comparando evidencias.
- Un mismo usuario no puede tener dos solicitudes abiertas sobre la misma ficha.
- Solo puede haber una solicitud aprobada por ficha, para siempre.

## 7.3 Flujo

```
El médico encuentra su ficha
        ↓
Se registra y verifica su correo          ← requisito previo
        ↓
Envía la solicitud, con licencia o documento
        ↓
Revisión administrativa
        ↓
Aprobación, en una sola transacción:
   la solicitud queda aprobada y con revisor
   la ficha gana administrador
   las solicitudes rivales quedan rechazadas
   si aportó licencia verificable, la ficha pasa a verificada
```

## 7.4 Seguridad

**Es la principal superficie de ataque del producto.** Quien se apodere de una ficha bien posicionada se queda con el tráfico orgánico de otro profesional.

Tres medidas obligatorias:

1. **Correo verificado** antes de poder solicitar.
2. **Límite de frecuencia** en el envío de solicitudes.
3. **El número de colegiado no basta.** Suele ser público, así que coincidir con él es condición necesaria pero no suficiente. Hace falta una segunda señal: correo del dominio del consultorio, o llamada al teléfono que ya figura en la ficha.

El atacante elegirá siempre fichas del grupo que sí tiene licencia registrada, precisamente porque ahí la comprobación automática es más tentadora de aceptar.

---

# 8. Verificación profesional

**Verificar es contrastar contra el registro oficial.** No es tener un número guardado.

Tres orígenes posibles, y los tres quedan registrados junto con el administrador que verificó y el momento en que lo hizo:

| Origen | Cuándo |
|---|---|
| Registro oficial | La ficha vino del padrón del colegio médico con su número |
| Documento | El médico aportó credencial y un administrador la revisó |
| Reclamación | Se resolvió al aprobar la solicitud |

**La verificación no se transfiere.** Lo que se verifica es un número de colegiado concreto. Si se cambia el número de una ficha verificada, la ficha vuelve a "sin verificar". Corregir el nombre no la afecta. Lo impone `UpdateDoctorAction`.

**Por qué la trazabilidad es obligatoria.** El posicionamiento del producto es la confianza. Si afirmamos públicamente que verificamos profesionales, tenemos que poder responder quién verificó a cada uno, cuándo y con qué. Sin esos datos, la insignia es una afirmación que no podemos sostener.

---

# 9. Bloqueo futuro conocido

**Hoy un usuario administra como máximo un médico.** La restricción está impuesta a nivel de base de datos y es correcta para Fase 1.

Bloquea dos actores que ya están previstos:

- La **secretaria** que opera la agenda de varios médicos.
- El **gestor de clínica** que administra a su plantilla.

**No hay que resolverlo ahora**, pero queda señalado porque el principio del proyecto lo exige. La salida es conocida y barata: retirar la restricción de unicidad e introducir una relación de muchos a muchos entre usuarios y médicos con un rol de gestión. Migración aditiva, sin pérdida de datos, en el momento en que el actor aparezca.

---

# 10. Auditoría

**Activa desde Fase 1.** En la versión 1.0 estaba marcada como futura; la decisión se revirtió.

**El motivo es simple: el historial es lo único que no se puede reconstruir hacia atrás.** Media jornada de trabajo ahora, imposible de recuperar después.

Se registran, como mínimo, la creación y modificación de fichas, su publicación y despublicación, la verificación, la suspensión, la fusión, el envío y la resolución de reclamaciones, la suspensión de cuentas, la asignación de roles, la aplicación de lotes de importación, la resolución manual de filas y el alta de supresiones.

**Caso que lo justifica por sí solo:** deshacer una fusión de médicos equivocada solo es posible con el estado previo de ambas fichas en el registro.

---

# 11. Decisiones

| Decisión | Estado | Motivo |
|---|---|---|
| User separado de Doctor | Vigente | Un Doctor existe sin cuenta; es la situación por defecto |
| Creador, administrador, verificador y solicitante son cuatro relaciones | Vigente | Confundirlas genera estados imposibles |
| Rol separado de User, permiso separado de Rol | Vigente | Evolución sin migraciones de esquema |
| Autorización por permiso, nunca por nombre de rol | Vigente | Un cambio de roles no debe obligar a tocar el código |
| Paquete de permisos en lugar de tablas propias | **Confirmado** | Caché de autorización e integración con el panel |
| Reclamación como entidad | Vigente (cambio respecto a 1.0) | Un estado no puede representar quién solicitó |
| Alcance editable acotado tras reclamar | Vigente (nuevo) | Sin ello la verificación sería un sello transferible |
| Trazabilidad de verificación | Vigente (nuevo) | El producto se posiciona en confianza |
| Auditoría en Fase 1 | Vigente (cambio respecto a 1.0) | El historial no se puede reconstruir |
| El importador no crea usuarios | Vigente (nuevo) | Se derivaba del principio fundacional pero no estaba escrito |
| Los usuarios no se borran físicamente | Vigente | Se suspenden; el borrado dejaría fichas en estado imposible |
| Un usuario administra un solo médico | Vigente, con bloqueo señalado | Correcto hoy; ver §9 |

---

# 12. Puntos abiertos

| # | Cuestión |
|---|---|
| 1 | ¿Qué segunda señal se exigirá para aprobar una reclamación además de la licencia? Correo corporativo, llamada al teléfono de la ficha, o ambas según el caso |
| 2 | ¿Se permitirá a un médico solicitar cambios en los campos bloqueados desde su panel, o el canal será externo? |
| 3 | ¿Habrá administradores con alcance limitado a un país? Hoy los roles son globales; acotarlos por país es una migración pequeña pero conviene anticiparla si se prevé un equipo por mercado |
