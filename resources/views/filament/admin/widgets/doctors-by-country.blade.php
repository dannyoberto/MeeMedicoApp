<x-filament-widgets::widget>
    <x-filament::section heading="Médicos por país">
        <ul class="flex flex-col gap-y-5">
            @foreach ($this->getRows() as $row)
                <li>
                    <div class="mb-2 flex items-baseline justify-between gap-3 text-sm">
                        <span class="text-gray-900 dark:text-white">{{ $row['name'] }}</span>
                        <span class="font-medium text-gray-900 tabular-nums dark:text-white">{{ number_format($row['total'], 0, ',', '.') }}</span>
                    </div>
                    <div class="h-1.5 w-full rounded-full bg-gray-100 dark:bg-white/10" role="presentation">
                        <div class="h-1.5 rounded-full bg-primary-700 dark:bg-primary-400" style="width: {{ $row['percent'] }}%"></div>
                    </div>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
