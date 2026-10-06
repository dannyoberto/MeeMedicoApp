<?php

namespace App\Filament\Resources\Facilities\Pages;

use App\Domain\Directory\Actions\CreateFacilityAction;
use App\Filament\Resources\Facilities\FacilityResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class CreateFacility extends CreateRecord
{
    protected static string $resource = FacilityResource::class;

    protected static ?string $title = 'Nuevo establecimiento';

    protected static bool $canCreateAnother = false;

    /** Campos del formulario que van a la primera sede. */
    private const LOCATION_FIELDS = ['city_id', 'address', 'address_2', 'latitude', 'longitude'];

    /** Campos del formulario que van a contactos, con su tipo. */
    private const CONTACT_FIELDS = ['phone_1' => 'phone', 'phone_2' => 'phone', 'email' => 'email'];

    protected function handleRecordCreation(array $data): Model
    {
        $contactFields = array_keys(array_filter(Arr::only($data, array_keys(self::CONTACT_FIELDS)), 'filled'));

        $payload = [
            ...Arr::except($data, [...self::LOCATION_FIELDS, ...array_keys(self::CONTACT_FIELDS)]),
            // El nombre del lugar es el del establecimiento: así la sede se encuentra buscándolo.
            'location' => ['name' => $data['name'], ...Arr::only($data, self::LOCATION_FIELDS)],
            'contacts' => array_map(fn (string $field) => ['type' => self::CONTACT_FIELDS[$field], 'value' => $data[$field]], $contactFields),
        ];

        try {
            return app(CreateFacilityAction::class)->execute($payload, auth()->user());
        } catch (ValidationException $e) {
            // La Action usa claves de dominio (location.city_id, contacts.0.value);
            // el formulario, sus propios campos (data.city_id, data.phone_1).
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(function ($messages, string $key) use ($contactFields) {
                $field = match (true) {
                    str_starts_with($key, 'location.') => substr($key, strlen('location.')),
                    (bool) preg_match('/^contacts\.(\d+)\./', $key, $m) => $contactFields[(int) $m[1]],
                    default => $key,
                };

                return ["data.{$field}" => $messages];
            })->all());
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Establecimiento creado en borrador.';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
