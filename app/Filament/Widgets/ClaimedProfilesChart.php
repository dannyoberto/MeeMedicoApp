<?php

namespace App\Filament\Widgets;

use App\Models\Doctor;
use Filament\Widgets\ChartWidget;

/**
 * Perfiles reclamados por semana (doctors.claimed_at). El claim es el motor de
 * adquisición de médicos (MODELO-IDENTIDAD.md §7.1): es la curva que hay que mirar.
 */
class ClaimedProfilesChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = ['md' => 2];

    protected ?string $heading = 'Perfiles reclamados por semana';

    protected ?string $description = 'Últimas 12 semanas';

    protected ?string $maxHeight = '260px';

    private const WEEKS = 12;

    public static function canView(): bool
    {
        return auth()->user()?->can('doctors.view') ?? false;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $tz = config('app.display_timezone');
        $start = now($tz)->startOfWeek()->subWeeks(self::WEEKS - 1);

        // Semanas en la zona del backoffice, no en UTC: un claim del domingo a las
        // 21:00 en Caracas pertenece a esa semana aunque en UTC ya sea lunes.
        $counts = Doctor::query()
            ->whereNotNull('claimed_at')
            ->where('claimed_at', '>=', $start->copy()->utc())
            ->selectRaw("date_trunc('week', claimed_at at time zone ?)::date as week, count(*) as total", [$tz])
            ->groupBy('week')
            ->pluck('total', 'week');

        $labels = [];
        $data = [];
        for ($i = 0; $i < self::WEEKS; $i++) {
            $week = $start->copy()->addWeeks($i);
            $labels[] = $week->locale('es')->translatedFormat('j M');
            $data[] = (int) ($counts[$week->toDateString()] ?? 0);
        }

        // petrol-300 para el histórico y petrol-700 para la semana en curso (mockup).
        $colors = array_fill(0, self::WEEKS - 1, '#7AAEBA');
        $colors[] = '#164D5B';

        return [
            'datasets' => [[
                'label' => 'Perfiles reclamados',
                'data' => $data,
                'backgroundColor' => $colors,
                'borderRadius' => 4,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => ['grid' => ['display' => false]],
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0], 'grid' => ['color' => '#EDF1F3']],
            ],
        ];
    }
}
