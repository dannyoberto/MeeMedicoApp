import { ArrowRightIcon, HeartIcon, MagnifyingGlassIcon, MapPinIcon, UserIcon } from '@heroicons/react/24/outline';
import { useEffect, useId, useRef, useState, type ComponentType, type KeyboardEvent, type SVGProps } from 'react';

/**
 * Buscador con sugerencias (combobox ARIA 1.2). Sustituye al <form> que renderiza
 * components/public/search-form.blade.php, con la misma marca y las mismas clases: sin
 * JavaScript ese formulario funciona igual, solo que sin sugerencias.
 *
 * La primera sugerencia es la interpretación de la frase entera ("cardiólogo en san
 * josé" → Cardiología en San José · 12 médicos), que lleva directo al listado. Enter
 * sin elegir nada envía el formulario a /{pais}/buscar, que hace la misma
 * interpretación en el servidor.
 *
 * Sin dependencias de estado servidor (TanStack Query): es una sola petición, cancelable,
 * y cada KB cuenta en el presupuesto de 100 KB (ARQUITECTURA.md §6.6).
 */

type Props = {
    country: string;
    action: string;
    value?: string | null;
    size?: 'lg' | 'sm';
    inputId?: string;
    placeholder?: string;
};

type Suggestion = { label: string; meta: string; url: string };

type SuggestResponse = {
    data: null | {
        interpretation: Suggestion | null;
        specialties: Suggestion[];
        cities: Suggestion[];
        doctors: Suggestion[];
    };
};

type Kind = 'interpretation' | 'specialty' | 'city' | 'doctor';

type Option = Suggestion & { kind: Kind; id: string };

const GROUPS: { key: Exclude<keyof NonNullable<SuggestResponse['data']>, 'interpretation'>; kind: Kind; title: string }[] = [
    { key: 'specialties', kind: 'specialty', title: 'Especialidades' },
    { key: 'cities', kind: 'city', title: 'Ciudades' },
    { key: 'doctors', kind: 'doctor', title: 'Médicos' },
];

const ICONS: Record<Kind, ComponentType<SVGProps<SVGSVGElement>>> = {
    interpretation: ArrowRightIcon,
    specialty: HeartIcon,
    city: MapPinIcon,
    doctor: UserIcon,
};

const MIN_LENGTH = 2;
const DEBOUNCE_MS = 150;

export default function SearchAutocomplete({
    country,
    action,
    value = '',
    size = 'lg',
    inputId = 'q',
    placeholder = 'Especialidad, ciudad o nombre',
}: Props) {
    const [query, setQuery] = useState(value ?? '');
    const [options, setOptions] = useState<Option[]>([]);
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(-1);
    const touched = useRef(false);
    const listId = useId();

    useEffect(() => {
        // No sugerir al montar con el valor que trae la página de resultados.
        if (!touched.current) {
            return;
        }

        const q = query.trim();
        if (q.length < MIN_LENGTH) {
            setOptions([]);
            setActive(-1);
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            try {
                const response = await fetch(`/api/v1/${country}/suggest?q=${encodeURIComponent(q)}`, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
                if (!response.ok) {
                    return;
                }
                const next = toOptions(((await response.json()) as SuggestResponse).data);
                setOptions(next);
                setActive(next[0]?.kind === 'interpretation' ? 0 : -1);
                setOpen(next.length > 0);
            } catch {
                // Cancelada o sin red: el formulario sigue funcionando sin sugerencias.
            }
        }, DEBOUNCE_MS);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [query, country]);

    const expanded = open && options.length > 0;
    const activeOption = expanded ? options[active] : undefined;

    function go(option: Option) {
        setOpen(false);
        window.location.assign(option.url);
    }

    function onKeyDown(event: KeyboardEvent<HTMLInputElement>) {
        switch (event.key) {
            case 'ArrowDown':
                if (options.length > 0) {
                    event.preventDefault();
                    setOpen(true);
                    setActive((i) => (i + 1) % options.length);
                }
                break;
            case 'ArrowUp':
                if (options.length > 0) {
                    event.preventDefault();
                    setOpen(true);
                    setActive((i) => (i <= 0 ? options.length - 1 : i - 1));
                }
                break;
            case 'Enter':
                if (activeOption) {
                    event.preventDefault();
                    go(activeOption);
                }
                break;
            case 'Escape':
                if (expanded) {
                    event.preventDefault();
                    setOpen(false);
                }
                break;
        }
    }

    let lastGroup: Kind | null = null;

    return (
        <form action={action} method="get" role="search" className={`search-form search-form-${size}`}>
            <div className="search-form-field">
                <label htmlFor={inputId} className="sr-only">
                    Buscar médicos
                </label>
                <MagnifyingGlassIcon className="search-form-icon" aria-hidden="true" />
                <input
                    id={inputId}
                    name="q"
                    type="search"
                    value={query}
                    className="input"
                    placeholder={placeholder}
                    autoComplete="off"
                    enterKeyHint="search"
                    maxLength={200}
                    role="combobox"
                    aria-autocomplete="list"
                    aria-expanded={expanded}
                    aria-controls={listId}
                    aria-activedescendant={activeOption ? `${listId}-${activeOption.id}` : undefined}
                    onChange={(event) => {
                        touched.current = true;
                        setQuery(event.target.value);
                        setOpen(true);
                    }}
                    onKeyDown={onKeyDown}
                    onFocus={() => setOpen(true)}
                    onBlur={() => setOpen(false)}
                />
            </div>
            <button type="submit" className="btn btn-primary">
                <MagnifyingGlassIcon className="size-5 sm:hidden" aria-hidden="true" />
                <span className="sr-only sm:not-sr-only">Buscar</span>
            </button>

            {/* Anclada al formulario, no al campo: en móvil necesita todo el ancho. */}
            <ul id={listId} role="listbox" aria-label="Sugerencias" className="search-suggestions" hidden={!expanded}>
                    {options.map((option, index) => {
                        const Icon = ICONS[option.kind];
                        const group = GROUPS.find((g) => g.kind === option.kind);
                        const heading = group && option.kind !== lastGroup ? group.title : null;
                        lastGroup = option.kind;

                        return [
                            heading && (
                                <li key={`h-${option.kind}`} role="presentation" className="search-suggestions-group">
                                    {heading}
                                </li>
                            ),
                            <li
                                key={option.id}
                                id={`${listId}-${option.id}`}
                                role="option"
                                aria-selected={index === active}
                                className={`search-suggestion${option.kind === 'interpretation' ? ' search-suggestion-smart' : ''}`}
                                // Mantiene el foco en el campo: el blur cerraría la lista antes del clic.
                                onMouseDown={(event) => event.preventDefault()}
                                onMouseEnter={() => setActive(index)}
                                onClick={() => go(option)}
                            >
                                <Icon className="search-suggestion-icon" aria-hidden="true" />
                                <span className="min-w-0 flex-1">{option.label}</span>
                                {option.meta && <span className="search-suggestion-meta">{option.meta}</span>}
                            </li>,
                        ];
                    })}
            </ul>

            <div className="sr-only" aria-live="polite">
                {expanded ? `${options.length} ${options.length === 1 ? 'sugerencia' : 'sugerencias'}` : ''}
            </div>
        </form>
    );
}

function toOptions(data: SuggestResponse['data']): Option[] {
    if (!data) {
        return [];
    }

    const options: Option[] = [];

    if (data.interpretation) {
        options.push({ ...data.interpretation, kind: 'interpretation', id: 'sug-interpretation' });
    }

    for (const group of GROUPS) {
        data[group.key].forEach((item, i) => options.push({ ...item, kind: group.kind, id: `sug-${group.kind}-${i}` }));
    }

    return options;
}
