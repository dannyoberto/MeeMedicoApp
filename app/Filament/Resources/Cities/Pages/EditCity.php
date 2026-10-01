<?php

namespace App\Filament\Resources\Cities\Pages;

use App\Filament\Actions\ChangeSlugAction;
use App\Filament\Actions\ToggleStatusAction;
use App\Filament\Resources\Cities\CityResource;
use Filament\Resources\Pages\EditRecord;

class EditCity extends EditRecord
{
    protected static string $resource = CityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ChangeSlugAction::make(),
            ToggleStatusAction::make(),
        ];
    }
}
