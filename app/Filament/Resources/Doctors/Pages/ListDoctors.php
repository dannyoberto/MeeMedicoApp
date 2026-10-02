<?php

namespace App\Filament\Resources\Doctors\Pages;

use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Enums\VerificationStatus;
use App\Domain\Directory\Support\PublicationRequirements;
use App\Filament\Resources\Doctors\DoctorResource;
use App\Models\Doctor;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

/**
 * El operador trabaja por colas, no por tablas: cada pestaña es una cola con su
 * contador (diferido, para no frenar la carga de la página).
 */
class ListDoctors extends ListRecords
{
    protected static string $resource = DoctorResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    /** Fichas que se pueden publicar: nunca lo estuvieron o se despublicaron. */
    private const UNPUBLISHED = [DoctorStatus::Draft, DoctorStatus::Inactive];

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todos'),
            'ready' => Tab::make('Listos para publicar')
                ->modifyQueryUsing(fn (Builder $query) => PublicationRequirements::whereReady($query->whereIn('status', self::UNPUBLISHED)))
                ->badge(fn () => PublicationRequirements::whereReady(Doctor::whereIn('status', self::UNPUBLISHED))->count() ?: null)
                ->badgeColor('success')
                ->deferBadge(),
            'incomplete' => Tab::make('Incompletos')
                ->modifyQueryUsing(fn (Builder $query) => PublicationRequirements::whereNotReady($query->whereIn('status', self::UNPUBLISHED)))
                ->badge(fn () => PublicationRequirements::whereNotReady(Doctor::whereIn('status', self::UNPUBLISHED))->count() ?: null)
                ->badgeColor('gray')
                ->deferBadge(),
            'verification' => Tab::make('Verificación pendiente')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('verification_status', VerificationStatus::Pending))
                ->badge(fn () => Doctor::where('verification_status', VerificationStatus::Pending)->count() ?: null)
                ->badgeColor('warning')
                ->deferBadge(),
            'unverified' => Tab::make('Publicados sin verificar')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', DoctorStatus::Active)
                    ->where('verification_status', '<>', VerificationStatus::Verified)),
            'suspended' => Tab::make('Suspendidos')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', DoctorStatus::Suspended))
                ->badge(fn () => Doctor::where('status', DoctorStatus::Suspended)->count() ?: null)
                ->badgeColor('danger')
                ->deferBadge(),
        ];
    }
}
