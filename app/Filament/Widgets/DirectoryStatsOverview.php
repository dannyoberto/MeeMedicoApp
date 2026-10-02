<?php

namespace App\Filament\Widgets;

use App\Domain\Claim\Enums\DoctorClaimStatus;
use App\Domain\Directory\Enums\ClaimStatus;
use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Enums\VerificationStatus;
use App\Filament\Resources\Doctors\DoctorResource;
use App\Models\Doctor;
use App\Models\DoctorClaim;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * KPIs de Fase 1. Los números se formatean en español (1.406, 38 %).
 */
class DirectoryStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('doctors.view') ?? false;
    }

    protected function getStats(): array
    {
        $published = Doctor::where('status', DoctorStatus::Active)->count();
        $publishedThisMonth = Doctor::where('status', DoctorStatus::Active)
            ->where('published_at', '>=', now()->startOfMonth())
            ->count();

        $claimed = Doctor::where('status', DoctorStatus::Active)
            ->where('claim_status', ClaimStatus::Claimed)
            ->count();
        $claimedRatio = $published > 0 ? round($claimed * 100 / $published) : 0;

        $pendingVerifications = Doctor::where('verification_status', VerificationStatus::Pending)->count();

        $pendingClaims = DoctorClaim::where('status', DoctorClaimStatus::Pending)->count();
        $staleClaims = DoctorClaim::where('status', DoctorClaimStatus::Pending)
            ->where('submitted_at', '<', now()->subHours(48))
            ->count();

        // Cada indicador lleva a la lista que lo explica. Las reclamaciones aún no tienen pantalla.
        return [
            Stat::make('Médicos publicados', self::number($published))
                ->description('+'.self::number($publishedThisMonth).' este mes')
                ->url(DoctorResource::getUrl('index', ['tab' => 'all', 'filters' => ['status' => ['value' => DoctorStatus::Active->value]]])),

            Stat::make('Perfiles reclamados', "{$claimedRatio} %")
                ->description(self::number($claimed).' de '.self::number($published).' fichas publicadas')
                ->url(DoctorResource::getUrl('index', ['tab' => 'all', 'filters' => ['claim_status' => ['value' => ClaimStatus::Claimed->value]]])),

            Stat::make('Verificaciones pendientes', self::number($pendingVerifications))
                ->description($pendingVerifications > 0 ? 'Por revisar' : 'Al día')
                ->color($pendingVerifications > 0 ? 'warning' : 'gray')
                ->url(DoctorResource::getUrl('index', ['tab' => 'verification'])),

            Stat::make('Reclamaciones pendientes', self::number($pendingClaims))
                ->description($staleClaims > 0 ? "{$staleClaims} con más de 48 h" : 'Ninguna con más de 48 h')
                ->color($staleClaims > 0 ? 'warning' : 'gray'),
        ];
    }

    private static function number(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }
}
