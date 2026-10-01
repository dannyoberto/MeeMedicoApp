<?php

namespace App\Filament\Resources\Specialties\Pages;

use App\Filament\Actions\ChangeSlugAction;
use App\Filament\Actions\ToggleStatusAction;
use App\Filament\Resources\Specialties\SpecialtyResource;
use Filament\Resources\Pages\EditRecord;

class EditSpecialty extends EditRecord
{
    protected static string $resource = SpecialtyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ChangeSlugAction::make(),
            ToggleStatusAction::make(),
        ];
    }
}
