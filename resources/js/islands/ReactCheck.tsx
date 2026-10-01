import { useState } from 'react';

type Props = {
    label?: string;
};

/**
 * TEMPORAL: comprueba que el montaje de islas funciona de punta a punta.
 * Se elimina, junto con su entrada en bootstrap-islands.ts y su contenedor en
 * welcome.blade.php, cuando exista la primera isla real.
 */
export default function ReactCheck({ label = 'React' }: Props) {
    const [clicks, setClicks] = useState(0);

    return (
        <button
            type="button"
            onClick={() => setClicks((n) => n + 1)}
            className="rounded-md border px-4 py-2 text-sm"
        >
            {label} funciona · clics: {clicks}
        </button>
    );
}
