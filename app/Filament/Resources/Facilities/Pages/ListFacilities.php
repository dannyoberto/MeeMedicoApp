<?php

namespace App\Filament\Resources\Facilities\Pages;

use App\Domain\Directory\Enums\FacilityStatus;
use App\Filament\Resources\Facilities\FacilityResource;
use App\Models\Facility;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListFacilities extends ListRecords
{
    protected static string $resource = FacilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $tab = fn (string $label, FacilityStatus $status, string $color) => Tab::make($label)
            ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status))
            ->badge(fn () => Facility::where('status', $status)->count() ?: null)
            ->badgeColor($color)
            ->deferBadge();

        return [
            'all' => Tab::make('Todos'),
            'active' => $tab('Activos', FacilityStatus::Active, 'success'),
            'draft' => $tab('Borradores', FacilityStatus::Draft, 'gray'),
            'inactive' => $tab('Inactivos', FacilityStatus::Inactive, 'gray'),
        ];
    }
}
