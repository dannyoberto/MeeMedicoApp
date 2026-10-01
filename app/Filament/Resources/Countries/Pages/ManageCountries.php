<?php

namespace App\Filament\Resources\Countries\Pages;

use App\Filament\Resources\Countries\CountryResource;
use Filament\Resources\Pages\ManageRecords;

class ManageCountries extends ManageRecords
{
    protected static string $resource = CountryResource::class;

    /**
     * Sin CreateAction: los países los define CountrySeeder.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
