<?php

namespace App\Filament\Resources\Activities\Pages;

use App\Filament\Resources\Activities\ActivityResource;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManageActivities extends ManageRecords
{
    protected static string $resource = ActivityResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    /**
     * Sin CreateAction: la auditoría solo la escriben las Actions de dominio.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
