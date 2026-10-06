<?php

namespace App\Filament\Resources\Facilities\Pages;

use App\Domain\Directory\Actions\CreateFacilityAction;
use App\Filament\Resources\Facilities\FacilityResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateFacility extends CreateRecord
{
    protected static string $resource = FacilityResource::class;

    protected static ?string $title = 'Nuevo establecimiento';

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateFacilityAction::class)->execute($data, auth()->user());
        } catch (ValidationException $e) {
            // La Action usa claves de dominio (network_id); el formulario, data.network_id.
            throw ValidationException::withMessages(
                collect($e->errors())->mapWithKeys(fn ($m, $key) => ["data.{$key}" => $m])->all(),
            );
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Establecimiento creado en borrador. Añade una sede y un contacto para activarlo.';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
