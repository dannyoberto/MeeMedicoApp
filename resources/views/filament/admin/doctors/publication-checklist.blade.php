{{-- Requisitos de PublicationRequirements (único lugar donde se define la regla). --}}
@php
    use App\Domain\Directory\Support\PublicationRequirements;

    $record = $getRecord();
    $checks = $record ? PublicationRequirements::check($record) : [];
    $ready = $checks && ! in_array(false, $checks, true);
@endphp

<div class="flex flex-col gap-y-3">
    <ul class="flex flex-col gap-y-2.5">
        @foreach (PublicationRequirements::LABELS as $key => $label)
            @php $ok = $checks[$key] ?? false; @endphp
            <li class="flex items-start gap-x-2 text-sm">
                @if ($ok)
                    <x-filament::icon icon="heroicon-m-check-circle" class="mt-0.5 size-5 shrink-0 text-success-600" />
                    <span class="text-gray-700 dark:text-gray-200">{{ $label }}</span>
                @else
                    <x-filament::icon icon="heroicon-m-x-circle" class="mt-0.5 size-5 shrink-0 text-gray-400" />
                    <span class="text-gray-500 dark:text-gray-400">{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ul>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        @if ($record?->status === \App\Domain\Directory\Enums\DoctorStatus::Active)
            Publicada: no se puede quitar lo último de cada requisito.
        @elseif ($ready)
            Lista para publicar.
        @else
            Complétala en las pestañas de abajo para poder publicarla.
        @endif
    </p>
</div>
