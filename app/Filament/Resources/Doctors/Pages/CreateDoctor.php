<?php

namespace App\Filament\Resources\Doctors\Pages;

use App\Domain\Directory\Actions\CreateDoctorAction;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use App\Filament\Resources\Doctors\DoctorResource;
use App\Filament\Support\DomainAction;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateDoctor extends CreateRecord
{
    protected static string $resource = DoctorResource::class;

    protected static ?string $title = 'Nueva ficha de médico';

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateDoctorAction::class)->execute($data, auth()->user(), (bool) ($data['confirm_homonym'] ?? false));
        } catch (DirectoryRuleException $e) {
            DomainAction::notify($e->getMessage(), $e->details);

            throw new Halt;
        } catch (ValidationException $e) {
            // La Action usa claves de dominio (license_number); el formulario, data.license_number.
            throw ValidationException::withMessages(
                collect($e->errors())->mapWithKeys(fn ($m, $key) => ["data.{$key}" => $m])->all(),
            );
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Ficha creada en borrador. Añade especialidad, ubicación y contacto para publicarla.';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
