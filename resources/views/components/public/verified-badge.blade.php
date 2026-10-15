{{--
    El único estado del perfil visible al paciente (design-system §4.4 y §8): verde +
    escudo + texto. Nunca oro: verificado no es premium.
--}}
<span {{ $attributes->class('badge badge-success') }} title="MeeMedico validó su licencia médica">
    <x-heroicon-m-shield-check class="size-4 shrink-0" aria-hidden="true" />
    Verificado
</span>
