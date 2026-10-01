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

type IslandModule = { default: ComponentType<Record<string, unknown>> };

const islands: Record<string, () => Promise<IslandModule>> = {
    ReactCheck: () => import('./islands/ReactCheck'),
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

export async function mountIslands(root: ParentNode = document): Promise<void> {
    const elements = [...root.querySelectorAll<HTMLElement>('[data-island]:not([data-island-mounted])')];
    if (elements.length === 0) {
        return;
    }

    const [{ createElement }, { createRoot }] = await Promise.all([import('react'), import('react-dom/client')]);

    await Promise.all(
        elements.map(async (el) => {
            const name = el.dataset.island ?? '';
            const load = islands[name];
            if (!load) {
                console.error(`Isla desconocida: "${name}"`);
                return;
            }

            el.dataset.islandMounted = 'true';
            const { default: Component } = await load();
            createRoot(el).render(createElement(Component, readProps(el)));
        }),
    );
}
