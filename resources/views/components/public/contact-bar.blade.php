{{--
    Barra fija de contacto en móvil (ficha): la acción que el paciente vino a hacer,
    siempre al alcance del pulgar. HTML y CSS; se oculta desde md, donde los contactos
    van en la tarjeta lateral. La página lleva .has-contact-bar para no tapar contenido.
--}}
@props(['doctor', 'primary', 'secondary' => null])
@if ($primary)
    <div class="contact-bar" role="region" aria-label="Contactar a {{ $doctor->displayName() }}">
        @if ($secondary)
            <x-public.contact-link :link="$secondary" :doctor="$doctor" class="btn-secondary" />
        @endif
        <x-public.contact-link :link="$primary" :doctor="$doctor" class="btn-primary" />
    </div>
@endif
