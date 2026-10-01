{{-- Detalle de una fila de importación: el dato original intacto, sus incidencias y los candidatos. --}}
@php
    use App\Domain\Import\Support\RowIssue;
    use App\Filament\Resources\Doctors\DoctorResource;

    $levels = [RowIssue::ERROR => 'danger', RowIssue::CONFLICT => 'warning', RowIssue::WARNING => 'gray'];
@endphp

<div class="flex flex-col gap-y-6 text-sm">
    @if ($row->validation_errors)
        <section>
            <h3 class="mb-2 font-semibold text-gray-900 dark:text-white">Incidencias</h3>
            <ul class="flex flex-col gap-y-2">
                @foreach ($row->validation_errors as $issue)
                    <li class="flex items-start gap-x-2">
                        <x-filament::badge :color="$levels[$issue['level']] ?? 'gray'" size="sm">
                            {{ ['error' => 'Error', 'conflict' => 'Conflicto', 'warning' => 'Aviso'][$issue['level']] ?? $issue['level'] }}
                        </x-filament::badge>
                        <span class="text-gray-700 dark:text-gray-200">{{ $issue['message'] }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($candidates->isNotEmpty())
        <section>
            <h3 class="mb-2 font-semibold text-gray-900 dark:text-white">Fichas que podrían ser la misma persona</h3>
            <ul class="flex flex-col gap-y-1.5">
                @foreach ($candidates as $doctor)
                    <li>
                        <a href="{{ DoctorResource::getUrl('edit', ['record' => $doctor]) }}" target="_blank" class="text-primary-600 hover:underline dark:text-primary-400">
                            {{ $doctor->first_name }} {{ $doctor->last_name }}
                        </a>
                        <span class="text-gray-500">· {{ $doctor->license_number ?? 'sin colegiado' }} · {{ $doctor->status->getLabel() }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section>
        <h3 class="mb-2 font-semibold text-gray-900 dark:text-white">Dato original (fila {{ $row->row_number }})</h3>
        <dl class="grid grid-cols-1 gap-x-4 gap-y-1.5 sm:grid-cols-[12rem_1fr]">
            @foreach ($row->raw_payload as $key => $value)
                <dt class="text-gray-500 dark:text-gray-400">{{ $key }}</dt>
                <dd class="break-words text-gray-900 dark:text-white">{{ $value }}</dd>
            @endforeach
        </dl>
    </section>
</div>
