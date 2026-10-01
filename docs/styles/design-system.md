# MeeMedico — Sistema de diseño

**Versión:** 0.1 (MVP)
**Ámbito:** sitio público (Blade + islas React) y backoffice (Filament 5)
**Archivos fuente:** `resources/css/tokens.css`, `resources/css/components.css`, `resources/css/app.css`

Este documento es la referencia para cualquier persona o agente (incluido Claude Code) que construya interfaz en MeeMedico. Si algo de este documento contradice el código, manda el código de tokens y este documento debe actualizarse.

---

## 1. Decisiones clave

| # | Decisión | Motivo |
|---|----------|--------|
| D1 | Primario **petróleo** (`#1B5E6F`), acento **oro** del logo (`#D4AF62`) | El petróleo comunica salud y confianza y combina con el oro. El oro del logo tiene 2,1:1 de contraste sobre blanco: no sirve para texto ni acciones. |
| D2 | Oro reservado a **marca y premium** | Si el oro aparece en todos lados, pierde su significado de "destacado". |
| D3 | **Verificado ≠ Premium**: verde + escudo vs. oro + corona | El paciente no debe interpretar que pagar equivale a ser confiable. Es un principio *trust-first*, no estético. |
| D4 | Tokens definidos **una sola vez** en CSS (`@theme`), consumidos por Blade y React vía utilidades Tailwind | Requisito del proyecto. No existe un objeto de tema en JavaScript. |
| D5 | Paleta por defecto de Tailwind **eliminada** (`--color-*: initial`) | Impide que alguien use `bg-pink-500` y rompa la coherencia. Solo existen los colores del sistema. |
| D6 | Componentes base como **clases CSS** (`.btn`, `.field`, `.badge`, `.alert`, `.card`) | Blade y React comparten exactamente el mismo estilo sin duplicar cadenas de utilidades. Funcionan sin JavaScript. |
| D7 | **Una sola familia tipográfica** (Inter variable, autoalojada) | Presupuesto de rendimiento y legibilidad. Se descarta una segunda fuente para títulos. |
| D8 | Filament con su layout **por defecto**, personalizado solo con colores, fuente y logo | Mantener Filament actualizable. Personalizarlo a fondo genera deuda en cada actualización mayor. |
| D9 | Las capturas de Creative Tim son **solo inspiración** | Sirven como inventario de patrones, no como estética. Filament ya resuelve esos patrones. |
| D10 | Iconos **Heroicons** en todo el producto | Es el set nativo de Filament; en Blade se renderiza como SVG inline (0 KB de JS). |

---

## 2. Principios

1. **Legibilidad antes que efecto.** Contraste AA como mínimo en todo texto y control. Nada de pesos *light*.
2. **El color significa algo.** Cada color comunica una acción o un estado; nunca decora.
3. **Funciona sin JavaScript.** Toda página pública es usable con el HTML y el CSS servidos. React solo mejora.
4. **Mobile-first y red lenta.** Diseñar primero para 360 px de ancho y 3G/4G inestable.
5. **Textos en español crecen.** Ningún componente puede romperse porque una etiqueta sea un 30 % más larga.
6. **Restricción.** Una acción primaria por vista. Menos variantes, menos divergencia entre pantallas.

---

## 3. Arquitectura de estilos

### 3.1 Dos mundos separados

| | Sitio público | Backoffice |
|---|---|---|
| Tecnología | Laravel 13 + Blade + islas React (TS) | Filament 5 |
| CSS | `resources/css/app.css` (tokens propios) | `resources/css/filament/admin/theme.css` (tema de Filament) |
| Fuente del color | `tokens.css` | `->colors()` en el PanelProvider |
| Prioridad | Rendimiento, SEO, confianza | Productividad, densidad |

Los valores hexadecimales de la marca se repiten en dos lugares: `tokens.css` y el PanelProvider de Filament. Es una duplicación **deliberada y acotada** (unos 7 colores), aceptable porque los paneles no comparten tema. La regla: si cambias un color de marca, cámbialo en ambos sitios en el mismo commit.

### 3.2 Las tres capas del sitio público

```
1. Primitivos   :root              --petrol-600, --gold-400, --gray-500 ...
                                   Valores crudos. NO generan utilidades.
2. Semánticos   @theme             --color-primary, --color-fg-muted ...
                                   Generan utilidades: bg-primary, text-fg-muted.
3. Componentes  @layer components  .btn, .field, .input, .badge, .alert, .card
```

**Regla de uso:**

- Plantillas Blade y componentes TSX usan **solo** la capa 2 (utilidades) y la capa 3 (clases de componente).
- La capa 1 solo se referencia desde `tokens.css` y, excepcionalmente, desde `components.css`.
- Prohibido: colores hexadecimales en plantillas, utilidades arbitrarias de color (`bg-[#123456]`) y `style="color: …"`.

**Por qué los primitivos no están en `@theme`:** si estuvieran, Tailwind generaría `bg-petrol-600`, `text-gold-400`, etc., y el equipo terminaría usando valores crudos en lugar de roles. Al dejarlos en `:root`, la única forma de pintar algo es a través de un rol semántico.

**Cambio global de estilo:** modificar un primitivo cambia todo lo que lo usa; modificar un semántico reasigna un rol (por ejemplo, `--color-primary` → otro primitivo) en todo el sitio. No hace falta tocar plantillas.

> Nota técnica: si al compilar alguna variable semántica no aparece en `:root` (por ejemplo, porque solo se usa desde un `style` inline de React), declarar el bloque como `@theme static` para forzar su emisión.

### 3.3 Estructura de archivos

```
resources/
  css/
    app.css            ← punto de entrada: importa Tailwind, tokens y componentes
    tokens.css         ← capas 1 y 2 + @font-face
    components.css     ← base + capa 3
    filament/admin/
      theme.css        ← generado por `php artisan make:filament-theme`
  views/components/ui/ ← componentes Blade (<x-ui.button>, <x-ui.badge> …)
  js/islands/          ← islas React (.tsx), sin CSS propio
public/fonts/
  InterVariable-latin.woff2
```

Las islas React **no importan CSS**. Usan las mismas clases que Blade, y `app.css` escanea `resources/js/**/*.tsx` con `@source`.

---

## 4. Color

### 4.1 Roles semánticos

| Token (utilidad) | Primitivo | Uso | Contraste |
|---|---|---|---|
| `canvas` | gray-50 | Fondo de página | — |
| `surface` | white | Tarjetas, formularios | — |
| `surface-muted` | gray-100 | Zonas secundarias, hover de filas | — |
| `surface-inverse` | petrol-800 | Header/footer de marca | — |
| `fg` | gray-900 | Texto principal | 15,9:1 |
| `fg-muted` | gray-600 | Texto secundario | 6,2:1 |
| `fg-subtle` | gray-500 | Metadatos, placeholder | 5,0:1 |
| `fg-link` | petrol-600 | Enlaces | 7,3:1 |
| `border` | gray-200 | Divisores decorativos | 1,3:1 (decorativo) |
| `border-input` | gray-500 | Borde de controles | 5,0:1 (≥ 3:1 exigido) |
| `primary` / `primary-fg` | petrol-600 / white | Botón primario | 7,3:1 |
| `primary-soft` / `primary-soft-fg` | petrol-50 / petrol-700 | Chips, selección | 8,5:1 |
| `brand` | gold-400 | Logo, icono premium, rellenos | — |
| `brand-fg` | gold-800 | Texto dorado sobre claro | 7,0:1 |
| `premium` / `premium-fg` | gold-50 / gold-800 | Distintivo premium | 6,6:1 |

Contrastes calculados sobre blanco (o sobre su fondo emparejado) según WCAG 2.x.

### 4.2 Tonos de estado

Cada tono tiene cuatro tokens: fondo (`success`), texto (`success-fg`), borde (`success-line`) y sólido (`success-solid`).

| Tono | Fondo / texto | Contraste | Uso típico |
|---|---|---|---|
| `success` | green-50 / green-800 | 8,9:1 | Verificado, completado |
| `warning` | amber-50 / amber-800 | 8,0:1 | Pendiente, requiere acción |
| `danger` | red-50 / red-800 | 10,2:1 | Error, suspendido, destructivo |
| `info` | blue-50 / blue-800 | 9,6:1 | Información, confirmado |
| `gray` | gray-100 / gray-700 | 7,6:1 | Neutro, inactivo |
| `primary` | petrol-50 / petrol-700 | 8,5:1 | Selección, destacado neutro |
| `premium` | gold-50 / gold-800 | 6,6:1 | Solo membresía premium |

Los nombres (`success`, `warning`, `danger`, `info`, `gray`, `primary`, `premium`) son **idénticos a los colores de Filament**. Esto permite que un solo enum PHP alimente ambos mundos (ver §8).

### 4.3 Reglas del oro

- ✅ Logo, isotipo, icono y distintivo premium, CTA "Hazte premium" dentro del panel del médico.
- ✅ Texto dorado solo con `brand-fg` (gold-800) sobre fondos claros.
- ❌ Nunca como color de botón primario, enlace o estado.
- ❌ Nunca `brand` (gold-400) como color de texto sobre blanco.
- ❌ Nunca oro en elementos de verificación o confianza.

### 4.4 Verificado vs. Premium

| | Verificado | Premium |
|---|---|---|
| Significa | MeeMedico validó la licencia médica | El médico paga una membresía |
| Tono | `success` (verde) | `premium` (oro) |
| Icono | `heroicon-o-shield-check` | `heroicon-o-star` |
| Texto | "Verificado" | "Perfil destacado" |
| Visible al paciente | Sí | Sí |

Ambos pueden coexistir en un mismo perfil, pero nunca deben parecerse. Si en el futuro se diseña un sello propio, el de verificación no puede usar dorado.

---

## 5. Tipografía

**Familia única:** Inter variable, autoalojada, subconjunto latino (cubre á é í ó ú ñ ü ¿ ¡). Un solo archivo `woff2` con pesos 400–600, precargado en el `<head>`:

```html
<link rel="preload" href="/fonts/InterVariable-latin.woff2" as="font" type="font/woff2" crossorigin>
```

`font-display: swap` más una fuente de respaldo con métricas ajustadas (`Inter Fallback`) para evitar saltos de layout. Las métricas de `tokens.css` son aproximadas y deben verificarse con fontaine o capsize antes de producción.

| Utilidad | Tamaño | Interlineado | Uso |
|---|---|---|---|
| `text-xs` | 13 px | 20 px | Metadatos, badges. **Mínimo absoluto.** |
| `text-sm` | 14 px | 22 px | Etiquetas, ayuda, texto secundario |
| `text-base` | 16 px | 26 px | Cuerpo. Todos los inputs. |
| `text-lg` | 18 px | 28 px | Lead, nombre del médico en tarjeta |
| `text-xl` | 20 px | 28 px | h3 |
| `text-2xl` | 24 px | 32 px | h2 móvil |
| `text-3xl` | 30 px | 38 px | h1 móvil / h2 escritorio |
| `text-4xl` | 36 px | 44 px | h1 escritorio |

**Pesos:** `font-normal` (400) para cuerpo, `font-medium` (500) para etiquetas y botones, `font-semibold` (600) para títulos. No existen *light* ni *bold*.

**Reglas:**

- Mayúsculas sostenidas prohibidas en botones y títulos (reducen la legibilidad en español). Usar mayúscula inicial.
- Títulos con `text-wrap: balance`; párrafos con `text-wrap: pretty` (ya configurado en la base).
- Ancho de lectura máximo: `max-w-prose` (42rem).

---

## 6. Espaciado, forma y layout

- **Espaciado:** escala por defecto de Tailwind (múltiplos de 4 px). Ritmo vertical entre secciones: `gap-8` en móvil, `gap-12` en escritorio.
- **Radios:** `rounded-sm` 6 px (badges), `rounded-md` 8 px (botones, inputs), `rounded-lg` 12 px (tarjetas), `rounded-xl` 16 px (paneles y hojas móviles), `rounded-full` (avatares).
- **Elevación:** bordes antes que sombras. `shadow-sm` solo en hover de tarjetas interactivas; `shadow-md` solo en elementos flotantes (menús, autocompletado). Sin gradientes en UI.
- **Contenedores:** `max-w-content` (72rem) para el sitio, `max-w-prose` (42rem) para texto y formularios de una columna.
- **Breakpoints:** los de Tailwind (`sm` 640, `md` 768, `lg` 1024, `xl` 1280). Se escribe siempre primero el estilo móvil y se amplía con prefijos.
- **Área táctil:** mínimo 44 × 44 px en todo elemento interactivo (ya incluido en `.btn`, `.input` y `summary`).

---

## 7. Componentes

### 7.1 Catálogo base (CSS puro, sin JS)

| Clase | Variantes | Notas |
|---|---|---|
| `.btn` | `btn-primary`, `btn-secondary`, `btn-ghost`, `btn-danger`, `btn-premium`, `btn-sm`, `btn-block` | Envuelve el texto en lugar de cortarlo. Una sola `btn-primary` por vista. |
| `.field` + `.field-label`, `.field-hint`, `.field-error` | — | Etiqueta siempre visible arriba del control. |
| `.input` | `input`, `select`, `textarea` | 16 px de texto (evita el zoom de iOS). `aria-invalid="true"` activa el estado de error. |
| `.badge` | `badge-{success,warning,danger,info,gray,primary,premium}` | Envuelve si el texto es largo. |
| `.alert` | `alert-{success,warning,danger,info}` | Fondo tenue + borde lateral. Sin bloques saturados. |
| `.card` | `card-interactive` | Padding 16 px en móvil y 24 px desde `md`. |
| `.disclosure` | — | Acordeón nativo con `<details>`/`<summary>`: funciona sin JS. |
| `.island` | `--island-min-h` | Contenedor de isla React que reserva altura para evitar CLS. |

Como estas clases viven en `components.css` y no son utilidades generadas, **siempre están incluidas** en el CSS compilado. Por eso se pueden construir dinámicamente (`badge-{{ $tone }}`) sin que Tailwind las elimine.

### 7.2 Componentes Blade

Envolver el catálogo en componentes Blade anónimos (`resources/views/components/ui/`), que solo añaden estructura y accesibilidad:

```blade
{{-- resources/views/components/ui/badge.blade.php --}}
@props(['tone' => 'gray', 'icon' => null])
<span {{ $attributes->class(['badge', "badge-{$tone}"]) }}>
    @if ($icon) <x-dynamic-component :component="$icon" class="size-4 shrink-0" aria-hidden="true" /> @endif
    {{ $slot }}
</span>
```

```blade
{{-- resources/views/components/ui/field.blade.php --}}
@props(['name', 'label', 'hint' => null])
@php $id = $attributes->get('id', $name); $error = $errors->first($name); @endphp
<div class="field">
    <label for="{{ $id }}" class="field-label">{{ $label }}</label>
    <input id="{{ $id }}" name="{{ $name }}" value="{{ old($name) }}"
           @if($hint || $error) aria-describedby="{{ $id }}-desc" @endif
           @if($error) aria-invalid="true" @endif
           {{ $attributes->class('input') }}>
    @if ($error) <p id="{{ $id }}-desc" class="field-error">{{ $error }}</p>
    @elseif ($hint) <p id="{{ $id }}-desc" class="field-hint">{{ $hint }}</p> @endif
</div>
```

### 7.3 Islas React

Una isla React se justifica solo si el comportamiento **no puede** resolverse con HTML y CSS: autocompletado, filtros en vivo, dashboard del médico y, a futuro, el mapa.

Reglas:

1. Blade renderiza primero una versión funcional sin JS (por ejemplo, el formulario de búsqueda como `<form method="GET">` que recarga la página de resultados). La isla la mejora.
2. El contenedor reserva altura con `.island` y `style="--island-min-h: 3rem"`.
3. La isla usa las mismas clases (`btn`, `input`, `badge`…) y utilidades semánticas. Sin CSS-in-JS, sin librerías de componentes con runtime.
4. Para comportamientos accesibles complejos (combobox, listbox), usar primitivas *headless* ligeras o implementación propia; medir el peso antes de añadir una dependencia.

### 7.4 Patrones que NO usa el sitio público

- Modales para flujos principales (en móvil, una página nueva es más clara y funciona sin JS).
- Carruseles.
- Tabs que ocultan contenido indexable (perjudican SEO); usar secciones con anclas o `<details>`.
- Toasts como único canal de feedback; los mensajes de formulario van junto al campo o en un `.alert` al inicio.

---

## 8. Estados del dominio → tonos

El mapeo de un estado del dominio a un tono vive **una sola vez**, en el enum PHP que modela ese estado. Filament lo consume de forma nativa (`HasColor`, `HasLabel`, `HasIcon`) y Blade lo consume a través de `<x-ui.badge>`. Así no hay tres tablas de colores que puedan divergir.

```php
// Ejemplo ilustrativo: los estados reales de perfil son una decisión de dominio pendiente.
enum DoctorProfileStatus: string implements HasLabel, HasColor, HasIcon
{
    case Unclaimed = 'unclaimed';
    case Claimed   = 'claimed';
    case Verified  = 'verified';
    case Suspended = 'suspended';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unclaimed => 'No reclamado',
            self::Claimed   => 'Reclamado',
            self::Verified  => 'Verificado',
            self::Suspended => 'Suspendido',
        };
    }

    public function getColor(): string   // mismo vocabulario que los tonos CSS
    {
        return match ($this) {
            self::Unclaimed => 'gray',
            self::Claimed   => 'warning',
            self::Verified  => 'success',
            self::Suspended => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Unclaimed => 'heroicon-o-user',
            self::Claimed   => 'heroicon-o-clock',
            self::Verified  => 'heroicon-o-shield-check',
            self::Suspended => 'heroicon-o-no-symbol',
        };
    }
}
```

```blade
<x-ui.badge :tone="$doctor->status->getColor()" :icon="$doctor->status->getIcon()">
    {{ $doctor->status->getLabel() }}
</x-ui.badge>
```

**Qué se muestra al paciente:** no todos los estados son públicos. En el sitio público solo se muestra "Verificado" (y "Perfil destacado" para premium). "No reclamado", "Reclamado" y "Suspendido" son estados operativos del backoffice. Esta es una decisión de producto que conviene confirmar.

**Referencia para fases futuras (no implementar ahora):** cuando existan citas, la guía sugiere `pendiente → warning`, `confirmada → info`, `completada → success`, `cancelada → gray`, `no asistió → danger`. Se documenta solo para que el vocabulario de tonos ya lo contemple.

---

## 9. Resiliencia de texto (español, multipaís)

- **Nunca anchos fijos** en botones, badges ni etiquetas. Usar `min-w`/`max-w`, nunca `w-32` para contener texto.
- **Envolver antes que truncar.** `truncate` y `line-clamp` solo en textos secundarios (biografía, descripción). Nunca en nombre del médico, especialidad, precio ni estado.
- **Hijos flex con texto** llevan `min-w-0` para poder encogerse.
- **Correos, URLs y nombres largos:** `break-words` / `overflow-wrap: anywhere` (ya incluido en `.badge`).
- **Botones en grupo:** `flex flex-wrap gap-2`; en móvil pueden apilarse con `btn-block`.
- **`<html lang="es">`** (o `es-CR`, `es-GT`, `es-DO` según el país) para lectura de pantalla y guionado correctos.
- **Probar con el texto más largo:** "Reclamar perfil profesional" o "Otorrinolaringología pediátrica" en lugar de "Guardar" o "Cardiología".
- **Números, moneda y fechas** con el formateador del país, nunca concatenados a mano. La moneda varía (CRC, GTQ, DOP), así que el ancho de un precio no es constante.

---

## 10. Rendimiento

| Recurso | Presupuesto | Cómo |
|---|---|---|
| JS público | < 100 KB comprimido | Solo islas, cargadas por página. Nada global. |
| CSS público | objetivo < 30 KB comprimido | Tailwind solo genera lo que se usa; paleta por defecto eliminada. |
| Fuentes | 1 archivo, ~40–50 KB | Inter variable, subconjunto latino, precargado. |
| LCP | < 2,5 s en móvil | HTML cacheado en CDN, CSS en `<head>`, imagen LCP con `fetchpriority="high"`, sin JS bloqueante. |
| CLS | < 0,1 | Fuente de respaldo con métricas, `width`/`height` en imágenes, `.island` con altura reservada. |

Imágenes de perfil: `<img>` con `width`, `height`, `loading="lazy"` (salvo la primera visible), AVIF/WebP con respaldo y tamaños con `srcset`.

---

## 11. Accesibilidad (checklist mínimo)

- [ ] Contraste AA: texto ≥ 4,5:1, texto grande y bordes de controles ≥ 3:1. Usar solo los tokens de esta guía garantiza esto.
- [ ] Foco visible en todo elemento interactivo (`:focus-visible` global en color `focus`). Nunca `outline: none` sin reemplazo.
- [ ] Toda `input` con `<label>` asociada. El placeholder es un ejemplo, no una etiqueta.
- [ ] El estado nunca se comunica solo con color: badge = color + icono + texto.
- [ ] Errores con `aria-invalid` y `aria-describedby`.
- [ ] Área táctil ≥ 44 px.
- [ ] `prefers-reduced-motion` respetado (ya en la base).
- [ ] Iconos decorativos con `aria-hidden="true"`; botones solo-icono con `aria-label`.

---

## 12. Backoffice (Filament 5)

### 12.1 Qué se personaliza

```php
// app/Providers/Filament/AdminPanelProvider.php
use Filament\Support\Colors\Color;

return $panel
    ->colors([
        'primary' => Color::hex('#1B5E6F'), // petrol-600
        'gray'    => Color::Slate,          // gris frío, cercano a la escala pública
        'success' => Color::hex('#1E7A4C'),
        'warning' => Color::hex('#A86A0B'),
        'danger'  => Color::hex('#B42318'),
        'info'    => Color::hex('#1F5FA8'),
        'premium' => Color::hex('#D4AF62'), // color personalizado para badges premium
    ])
    // Sin ->font(): Filament 5 ya sirve Inter autoalojada por defecto (LocalFontProvider)
    ->brandLogo(asset('images/brand/logo-horizontal-ink.svg'))
    ->darkModeBrandLogo(asset('images/brand/logo-horizontal-gold.svg'))
    ->brandLogoHeight('2rem')
    ->favicon(asset('images/brand/isotipo.svg'))
    ->viteTheme('resources/css/filament/admin/theme.css');
```

- `Color::hex()` genera la escala 50–950 a partir de un solo valor; no coincidirá exactamente con las escalas públicas, y está bien. Revisar el contraste de los badges generados en una pantalla de prueba.
- `theme.css` (generado con `php artisan make:filament-theme`) queda casi vacío. Solo se añaden ajustes puntuales mediante las clases `.fi-*` cuando sea imprescindible.
- **Implementación actual** (referencia visual: `docs/Backoffice · Panel (Filament con tokens MeeMedico)@1x.png`):
  - `App\Filament\Support\BrandColors` pasa a Filament las **escalas exactas** de `tokens.css`. Los tonos de estado fijan sus valores 50, 200, 600 y 800.
  - `resources/css/filament/admin/theme.css` ajusta solo clases `.fi-*`: barra lateral blanca con borde, elemento activo en `primary-50`, tarjetas y tablas con borde y sin sombra, cabecera de tabla en `gray-50`, badges con fondo 50, borde 200 y texto 800, y borde de controles con contraste ≥ 3:1.
  - El menú tiene tres grupos: **Directorio**, **Operación** y **Plataforma**.
  - El Panel usa la composición del mockup con métricas de Fase 1: publicados, reclamados, verificaciones y reclamaciones pendientes, reclamados por semana y médicos por país. Premium, reviews, clínicas y SEO **no** se muestran hasta su fase.
  - Los avatares son de iniciales generadas en el servidor (`InitialsAvatarProvider`). El proveedor por defecto de Filament envía los nombres de los usuarios a ui-avatars.com.
- **Fuente:** resuelto. Filament 5 usa Inter autoalojada por defecto (`public/fonts/filament`). Llamar a `->font('Inter')` la cambiaría a Bunny Fonts (remota), así que no se llama.
- **Logo y favicon:** `AdminPanelProvider::brand()` los usa solo si existen en `public/images/brand/`. Mientras falten, el panel muestra "MeeMedico" como texto.
- **Fechas:** se muestran en `DISPLAY_TIMEZONE` (por defecto `America/Caracas`) mediante `FilamentTimezone`. La base sigue en UTC.

### 12.2 Qué NO se personaliza

- No se replica el look de Creative Tim (headers flotantes con gradiente, sidebar con foto, colores arcoíris).
- No se fuerza un sidebar oscuro: exige sobreescribir muchas clases internas y se rompe con cada actualización mayor.
- No se sustituyen tablas, formularios ni notificaciones de Filament por componentes propios.

### 12.3 De las capturas de inspiración a Filament

| Captura (Creative Tim) | Equivalente en Filament | Nota |
|---|---|---|
| Dashboard con tarjetas KPI | Stats Overview Widget | KPIs del MVP: perfiles reclamados, conversión premium, reviews. |
| Gráficas de línea y barras | Chart Widget | Una sola serie en color primario; comparaciones en gris + primario. Evitar gráficos de pastel. |
| React Table | Table Builder | Paginación server-side, filtros, acciones masivas y columnas de estado con badges del enum. |
| Formularios apilados / horizontales | Form Builder | Etiqueta arriba. Secciones y tabs de Filament para la ficha del médico. |
| Notificaciones y alertas | Notifications | Usar los tonos semánticos, no colores libres. |
| Tabs con iconos ("Page subcategories") | Tabs del Form Builder | Encaja con la edición del perfil: Descripción, Ubicación, Información legal. |
| Mapas | — | Fuera de alcance por ahora. |
| Botones sociales | — | Solo login social si se decide; Twitter y Google+ se descartan. |

---

## 13. Logo e iconografía

Variantes necesarias:

| Variante | Uso |
|---|---|
| Horizontal oro (original) | Solo sobre `surface-inverse` (petróleo oscuro): 5,7:1 |
| Horizontal tinta (monocromo petrol-800) | Sobre fondos claros: header público, Filament, correos |
| Isotipo oro / tinta | Favicon, avatar por defecto, futura app, futuro pin de mapa |

El logo original en oro **no debe usarse sobre blanco**: la palabra "Medico" en peso ligero pierde legibilidad (2,1:1).

Iconos: Heroicons *outline* de 20–24 px en interfaz, *mini* de 16 px en badges. En Blade, mediante `blade-ui-kit/blade-heroicons` (SVG inline). En React, `@heroicons/react` con importación individual por icono.

---

## 14. Gobernanza

**Cambiar un estilo globalmente:**

1. ¿Cambia el valor de un color? → editar el primitivo en `tokens.css` (y el PanelProvider si es de marca).
2. ¿Cambia qué color cumple un rol? → reasignar el token semántico en `@theme`.
3. ¿Cambia cómo se ve un componente? → editar su clase en `components.css`.
4. Verificar el contraste de cualquier color nuevo antes de hacer merge.

**Prohibido en plantillas y TSX:**

- Hexadecimales, `rgb()` o utilidades arbitrarias de color (`text-[#…]`).
- `style="…"` para color, tipografía o espaciado (excepto variables como `--island-min-h`).
- Crear variantes nuevas de botón o badge sin añadirlas antes a esta guía.
- Importar CSS desde una isla React.

**Checklist de PR (UI):**

- [ ] Solo tokens semánticos y clases de componente.
- [ ] Funciona sin JS (probar con JavaScript desactivado).
- [ ] Probado a 360 px y con la etiqueta más larga posible.
- [ ] Foco visible y navegable con teclado.
- [ ] No suma JS a páginas que no lo necesitan.

---

## 15. Fuera de alcance del MVP

| Tema | Estado | Cómo queda preparado |
|---|---|---|
| Modo oscuro público | No se implementa | Los tokens semánticos se pueden redefinir bajo `[data-theme="dark"]` sin tocar plantillas. |
| Mapa | Diferido | El isotipo ya está definido como base del pin (tinta = free, oro = premium). |
| Plantillas de correo | Fase de reservas | Usarán los mismos valores hexadecimales de los primitivos (los correos no soportan variables CSS). |
| App nativa | V2 | Los primitivos y semánticos se pueden exportar a otro formato cuando haga falta. |
| Tema por país | No previsto | Una sola marca para todos los países; solo cambian formatos locales. |

---

## 16. Supuestos y pendientes

- **Supuesto:** la licencia de Inter (SIL OFL) permite autoalojarla; verificar el subconjunto final.
- **Pendiente:** archivos SVG de las variantes del logo (tinta y oro) e isotipo aislado.
- **Pendiente:** confirmar qué estados del perfil existen y cuáles son visibles al paciente (§8).
- **Pendiente:** métricas exactas de `Inter Fallback`.
- ~~Pendiente: API exacta de fuentes en Filament 5.~~ Resuelto en §12.1.
