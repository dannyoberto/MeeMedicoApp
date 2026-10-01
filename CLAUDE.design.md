# Reglas de UI para Claude Code — MeeMedico

> Pegar esta sección en el `CLAUDE.md` del repositorio, o importarla con `@CLAUDE.design.md`.
> Referencia completa: @docs/design-system.md

## Antes de escribir UI
- Lee `docs/design-system.md` y `resources/css/tokens.css`.
- Identifica si la tarea es del **sitio público** (Blade + islas React) o del **backoffice** (Filament). Tienen temas separados.

## Sitio público (Blade + React)
- Usa solo utilidades semánticas: `bg-surface`, `text-fg`, `text-fg-muted`, `border-border-input`, `bg-primary`, `text-primary-fg`, etc.
- Usa las clases de componente existentes: `btn btn-primary`, `field`, `input`, `badge badge-{tone}`, `alert alert-{tone}`, `card`, `disclosure`.
- Tonos válidos: `success`, `warning`, `danger`, `info`, `gray`, `primary`, `premium`. No inventes otros.
- Prohibido: hexadecimales, `rgb()`, utilidades arbitrarias de color (`bg-[#…]`), `style=""` para estilos visuales, colores de la paleta por defecto de Tailwind (no existen).
- Mobile-first: estilos base para móvil, luego `sm:`, `md:`, `lg:`.
- Toda página debe funcionar sin JavaScript. Formularios con `method="GET"`/`POST` reales; acordeones con `<details>`.
- Islas React solo para autocompletado, filtros en vivo y dashboard del médico. Sin CSS propio, sin librerías de componentes con runtime, sin CSS-in-JS. Contenedor con `.island` y altura reservada.
- Presupuesto: < 100 KB de JS comprimido por página pública. Justifica cualquier dependencia nueva con su peso.
- Textos en español: sin anchos fijos, sin `truncate` en nombres, especialidades, precios o estados; `min-w-0` en hijos flex con texto.
- Accesibilidad: `<label>` visible en cada campo, estado = color + icono + texto, `aria-invalid`/`aria-describedby` en errores, área táctil ≥ 44 px.
- Oro (`brand`, `premium`) solo para marca y membresía premium. Nunca para acciones, enlaces ni verificación.
- "Verificado" usa `success` + `heroicon-o-shield-check`. "Premium" usa `premium` + `heroicon-o-star`. No los mezcles.
- Iconos: Heroicons (`<x-heroicon-o-…>` en Blade, `@heroicons/react` en TSX).

## Backoffice (Filament 5)
- Usa los componentes nativos de Filament (tablas, formularios, widgets, notificaciones). No los reemplaces.
- Colores por nombre semántico de Filament (`primary`, `success`, `warning`, `danger`, `info`, `gray`, `premium`), nunca hexadecimales.
- Estados del dominio: enum PHP con `HasLabel`, `HasColor`, `HasIcon`. El mismo enum alimenta Filament y los badges Blade.
- No sobreescribas clases `.fi-*` salvo que sea imprescindible, y documenta el motivo.

## Si necesitas algo que la guía no cubre
- No lo improvises en la plantilla. Propón el token o la variante nueva, indica su contraste y dónde se agregaría (`tokens.css` o `components.css`), y espera confirmación.
