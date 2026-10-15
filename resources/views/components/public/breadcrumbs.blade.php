{{-- Las mismas migas que el JSON-LD BreadcrumbList (App\Http\Support\Breadcrumbs). --}}
@props(['items'])
<nav aria-label="Ruta de navegación" {{ $attributes->class('text-sm') }}>
    <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-fg-muted">
        @foreach ($items as $item)
            <li class="flex min-w-0 items-center gap-1.5">
                @unless ($loop->first)
                    <x-heroicon-m-chevron-right class="size-4 shrink-0 text-fg-subtle" aria-hidden="true" />
                @endunless
                @if ($loop->last)
                    <span aria-current="page" class="text-fg">{{ $item['name'] }}</span>
                @else
                    <a href="{{ $item['url'] }}" class="text-fg-muted underline-offset-4 hover:text-fg-link hover:underline">{{ $item['name'] }}</a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
