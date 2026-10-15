{{--
    Enlace de contacto (tel:, wa.me, mailto:, web). data-contact lo recoge el beacon de
    resources/js/contact-events.ts y lo registra en doctor_contact_events
    (DATABASE.md §14.3): "te contactaron N veces" no se puede reconstruir hacia atrás.
--}}
@props([
    'link',
    'doctor',
    'source' => 'profile',
    'showValue' => false,
])
<a
    href="{{ $link->href }}"
    data-contact="{{ $link->event->value }}"
    data-doctor="{{ $doctor->id }}"
    data-source="{{ $source }}"
    @if ($link->external) target="_blank" rel="noopener nofollow" @endif
    {{ $attributes->class('btn') }}
>
    <x-dynamic-component :component="$link->icon()" class="size-5 shrink-0" aria-hidden="true" />
    @if ($showValue)
        <span class="min-w-0 text-left">
            <span class="block">{{ $link->label() }}</span>
            <span class="block text-sm font-normal opacity-80 [overflow-wrap:anywhere]">{{ $link->display }}</span>
        </span>
    @else
        <span>{{ $link->label() }}</span>
    @endif
</a>
