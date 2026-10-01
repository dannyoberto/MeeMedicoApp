<?php

namespace App\Filament\Resources\DoctorSuppressions\Pages;

use App\Filament\Resources\DoctorSuppressions\DoctorSuppressionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDoctorSuppressions extends ListRecords
{
    protected static string $resource = DoctorSuppressionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
