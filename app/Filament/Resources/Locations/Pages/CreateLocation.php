<?php

namespace App\Filament\Resources\Locations\Pages;

use App\Domain\Directory\Actions\SaveLocationAction;
use App\Filament\Resources\Locations\LocationResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateLocation extends CreateRecord
{
    protected static string $resource = LocationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(SaveLocationAction::class)->create($data, auth()->user());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(
                collect($e->errors())->mapWithKeys(fn ($m, $key) => ["data.{$key}" => $m])->all(),
            );
        }
    }
}
