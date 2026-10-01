<?php

namespace App\Filament\Resources\DoctorSuppressions\Pages;

use App\Domain\Directory\Actions\CreateSuppressionAction;
use App\Domain\Directory\Support\SuppressionMatches;
use App\Filament\Resources\DoctorSuppressions\DoctorSuppressionResource;
use App\Models\Country;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CreateDoctorSuppression extends CreateRecord
{
    protected static string $resource = DoctorSuppressionResource::class;

    protected static ?string $title = 'Registrar supresión';

    protected static bool $canCreateAnother = false;

    /**
     * Claves de error de la Action → campos del formulario.
     */
    private const ERROR_FIELDS = [
        'phone' => 'data.phone',
        'email' => 'data.email',
        'keys' => 'data.license_number',
    ];

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateSuppressionAction::class)->execute(
                Country::findOrFail($data['country_id']),
                $data['license_number'] ?? null,
                $data['email'] ?? null,
                $data['phone'] ?? null,
                $data['name'] ?? null,
                $data['reason'] ?? null,
                Carbon::parse($data['requested_at']),
                auth()->user(),
            );
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(
                collect($e->errors())->mapWithKeys(fn ($messages, $key) => [self::ERROR_FIELDS[$key] ?? "data.{$key}" => $messages])->all(),
            );
        }
    }

    protected function getCreatedNotification(): ?Notification
    {
        $matches = SuppressionMatches::for($this->getRecord())->count();

        return Notification::make()
            ->success()
            ->title('Supresión registrada')
            ->body($matches === 0
                ? 'Ninguna ficha existente coincide. El importador no la creará.'
                : "{$matches} ficha(s) existente(s) coinciden: revísalas en el detalle.")
            ->persistent($matches > 0);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
