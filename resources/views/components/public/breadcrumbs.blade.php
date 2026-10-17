{{-- Las mismas migas que el JSON-LD BreadcrumbList (App\Http\Support\Breadcrumbs).
     $inverse: sobre banda oscura. En móvil solo se ve la penúltima ("‹ Barquisimeto"):
     tres líneas de migas empujaban el contenido. --}}
@props(['items', 'inverse' => false])
@php $back = count($items) > 1 ? $items[count($items) - 2] : null; @endphp
<nav aria-label="Ruta de navegación" {{ $attributes->class('text-sm') }}>
    @if ($back)
        <a href="{{ $back['url'] }}" @class([
            'inline-flex min-h-11 items-center gap-1 sm:hidden',
            'text-muted-inverse' => $inverse,
            'text-fg-muted' => ! $inverse,
        ])>
            <x-heroicon-m-chevron-left class="size-4 shrink-0" aria-hidden="true" />
            {{ $back['name'] }}
        </a>
    @endif
    <ol @class([
        'hidden flex-wrap items-center gap-x-1.5 gap-y-1 sm:flex',
        'text-muted-inverse' => $inverse,
        'text-fg-muted' => ! $inverse,
    ])>
        @foreach ($items as $item)
            <li class="flex min-w-0 items-center gap-1.5">
                @unless ($loop->first)
                    <x-heroicon-m-chevron-right @class(['size-4 shrink-0', 'opacity-60' => $inverse, 'text-fg-subtle' => ! $inverse]) aria-hidden="true" />
                @endunless
                @if ($loop->last)
                    <span aria-current="page" @class(['text-fg-inverse' => $inverse, 'text-fg' => ! $inverse])>{{ $item['name'] }}</span>
                @else
                    <a href="{{ $item['url'] }}" @class([
                        'underline-offset-4 hover:underline',
                        'text-muted-inverse hover:text-fg-inverse' => $inverse,
                        'text-fg-muted hover:text-fg-link' => ! $inverse,
                    ])>{{ $item['name'] }}</a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
