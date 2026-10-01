@AGENTS.md

## Claude Code

Las instrucciones del proyecto están en `AGENTS.md`, importado arriba. Este archivo solo añade lo específico de Claude Code.

### Usa plan mode para

- Migraciones de base de datos y cambios de esquema.
- El pipeline de importación (`app/Domain/Import/`).
- Cualquier cambio en las Actions que imponen invariantes: `PublishDoctorAction`, `ApproveClaimAction`, `MergeDoctorsAction`.

Son las tres áreas donde un error se propaga a datos reales y no se deshace con un revert.

### Antes de proponer un cambio de esquema

Lee `docs/DATABASE.md` completo, no solo la tabla afectada. Las restricciones están repartidas entre índices parciales, `CHECK` y FK compuestas, y varias invariantes cruzan tablas.

### Skills del proyecto

Viven en `.claude/skills/`. Claude Code no lee nada bajo `.agents/`.

### Al terminar

No hagas commit ni push sin que te lo pidan explícitamente.
