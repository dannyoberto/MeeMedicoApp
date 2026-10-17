{{--
    Versión móvil de hero-media: recorte horizontal ligero (38 KB) con una tarjeta
    flotante. Solo por debajo de lg; en escritorio la fuente no coincide y el <img> se
    queda con un GIF de 1 px, así que no se descarga dos veces.
    Foto de ilustración: la tarjeta habla del directorio, no de la persona.
--}}
<div {{ $attributes->class('relative mb-8') }} aria-hidden="true">
    <picture>
        <source media="(max-width: 63.99rem)" type="image/webp" srcset="/images/home/hero-wide-640.webp">
        <img
            src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"
            alt=""
            width="640"
            height="400"
            loading="lazy"
            decoding="async"
            class="aspect-[8/5] w-full rounded-[1.5rem] bg-surface-muted object-cover"
        >
    </picture>
    <div class="float-card -bottom-7 right-4">
        <span class="float-card-icon bg-premium text-brand-fg">
            <x-heroicon-o-chat-bubble-left-right class="size-5" />
        </span>
        <span>
            <span class="block text-sm font-semibold text-fg">Llama o escribe por WhatsApp</span>
            <span class="block text-xs text-fg-muted">Directo al consultorio</span>
        </span>
    </div>
</div>
