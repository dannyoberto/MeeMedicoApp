<?php

namespace App\Filament\Resources\Insurers\Pages;

use App\Filament\Resources\Insurers\InsurerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * Se crea en un modal (cuatro campos) y se edita en su página, que tiene las pestañas
 * de médicos y establecimientos (design-system.md §12.4).
 */
class ListInsurers extends ListRecords
{
    protected static string $resource = InsurerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
