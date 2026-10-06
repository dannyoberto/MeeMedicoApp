<?php

namespace App\Filament\Resources\FacilityNetworks\Pages;

use App\Filament\Resources\FacilityNetworks\FacilityNetworkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

/**
 * Una red son cuatro campos y no tiene sublistas: se crea y se edita en un modal,
 * sin salir de la lista (design-system.md §12.4).
 */
class ManageFacilityNetworks extends ManageRecords
{
    protected static string $resource = FacilityNetworkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
