/**
 * Monta las islas de React presentes en el HTML servido por Blade (ARQUITECTURA.md §9.2).
 *
 *   <div data-island="SearchMap" data-props='@json($props)'></div>
 *
 * React y cada isla se cargan con import() dinámico: una página sin islas (la mayoría
 * de las fichas) no descarga React, y una con islas solo descarga las suyas. Es lo
 * que mantiene el presupuesto de 100 KB (§6.6).
 */
import type { ComponentType } from 'react';

// Los props llegan como JSON desde Blade (data-props): no hay tipo común. El contrato lo
// declara cada isla en su tipo Props, y el componente Blade que la monta lo respeta.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
type IslandModule = { default: ComponentType<any> };

const islands: Record<string, () => Promise<IslandModule>> = {
    SearchAutocomplete: () => import('./islands/SearchAutocomplete'),
};

function readProps(el: HTMLElement): Record<string, unknown> {
    const raw = el.dataset.props;
    if (!raw) {
        return {};
    }

    try {
        return JSON.parse(raw) as Record<string, unknown>;
    } catch {
        console.error(`data-props inválido en la isla "${el.dataset.island}"`);
        return {};
    }
}

/**
 * data-island-load="interaction": React no se descarga al cargar la página, sino cuando el
 * usuario se acerca a la isla (ratón encima, foco o toque). La versión Blade funciona
 * mientras tanto. Sin esto, la home del país pagaba ~70 KB de React antes del LCP
 * (de 2,3 s a 3,9 s en Lighthouse móvil).
 */
const INTERACTION_EVENTS = ['pointerenter', 'focusin', 'touchstart'] as const;

export function mountIslands(root: ParentNode = document): void {
    for (const el of root.querySelectorAll<HTMLElement>('[data-island]:not([data-island-mounted])')) {
        if (el.dataset.islandLoad !== 'interaction') {
            void mount(el, false);
            continue;
        }

        const onInteraction = () => {
            INTERACTION_EVENTS.forEach((type) => el.removeEventListener(type, onInteraction));
            void mount(el, true);
        };
        INTERACTION_EVENTS.forEach((type) => el.addEventListener(type, onInteraction, { passive: true }));
    }
}

async function mount(el: HTMLElement, onInteraction: boolean): Promise<void> {
    const name = el.dataset.island ?? '';
    const load = islands[name];
    if (!load) {
        console.error(`Isla desconocida: "${name}"`);
        return;
    }

    el.dataset.islandMounted = 'true';
    const [{ createElement }, { createRoot }, { default: Component }] = await Promise.all([
        import('react'),
        import('react-dom/client'),
        load(),
    ]);

    const props = readProps(el);

    // Lo que el usuario escribió en el formulario Blade mientras React se descargaba no se
    // pierde: la isla arranca con ese valor y con el foco donde estaba.
    if (onInteraction) {
        const input = el.querySelector('input');
        if (input) {
            props.value = input.value;
            props.activate = document.activeElement === input;
        }
    }

    createRoot(el).render(createElement(Component, props));
}
