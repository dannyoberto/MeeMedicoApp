<?php

namespace App\Filament\Widgets;

use App\Domain\Directory\Enums\DoctorStatus;
use App\Models\Country;
use Filament\Widgets\Widget;

/**
 * Médicos publicados por país, con barras proporcionales al país con más fichas.
 */
class DoctorsByCountry extends Widget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.admin.widgets.doctors-by-country';

    public static function canView(): bool
    {
        return auth()->user()?->can('doctors.view') ?? false;
    }

    /**
     * @return array<int, array{name: string, total: int, percent: int}>
     */
    public function getRows(): array
    {
        $countries = Country::query()
            ->withCount(['doctors' => fn ($q) => $q->where('status', DoctorStatus::Active)])
            ->orderByDesc('doctors_count')
            ->orderBy('name')
            ->get();

        $max = max(1, (int) $countries->max('doctors_count'));

        return $countries->map(fn (Country $country) => [
            'name' => $country->name,
            'total' => $country->doctors_count,
            'percent' => (int) round($country->doctors_count * 100 / $max),
        ])->all();
    }
}
