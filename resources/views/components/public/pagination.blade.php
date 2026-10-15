{{--
    Paginación con enlaces normales (rel prev/next): rastreable y sin JavaScript.
    $url recibe el número de página y devuelve su URL canónica (PublicUrl).
--}}
@props(['paginator', 'url'])
@if ($paginator->hasPages())
    <nav aria-label="Paginación" {{ $attributes->class('mt-8 flex items-center justify-between gap-4') }}>
        @if ($paginator->onFirstPage())
            <span></span>
        @else
            <a href="{{ $url($paginator->currentPage() - 1) }}" rel="prev" class="btn btn-secondary">
                <x-heroicon-m-arrow-left class="size-5" aria-hidden="true" />
                Anterior
            </a>
        @endif

        <p class="text-sm text-fg-muted">Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</p>

        @if ($paginator->hasMorePages())
            <a href="{{ $url($paginator->currentPage() + 1) }}" rel="next" class="btn btn-secondary">
                Siguiente
                <x-heroicon-m-arrow-right class="size-5" aria-hidden="true" />
            </a>
        @else
            <span></span>
        @endif
    </nav>
@endif
