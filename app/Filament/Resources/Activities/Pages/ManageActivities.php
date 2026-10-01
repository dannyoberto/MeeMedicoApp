<?php

namespace App\Filament\Resources\Activities\Pages;

use App\Filament\Resources\Activities\ActivityResource;
use Filament\Resources\Pages\ManageRecords;

class ManageActivities extends ManageRecords
{
    protected static string $resource = ActivityResource::class;

    /**
     * Sin CreateAction: la auditoría solo la escriben las Actions de dominio.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
