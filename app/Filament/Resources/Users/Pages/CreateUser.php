<?php

namespace App\Filament\Resources\Users\Pages;

use App\Domain\Identity\Actions\InviteUserAction;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Invitar usuario';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(InviteUserAction::class)->execute(
                $data['name'],
                $data['email'],
                $data['roles'] ?? [],
                auth()->user(),
            );
        } catch (ValidationException $e) {
            // La Action usa claves de dominio (email); el formulario, data.email.
            throw ValidationException::withMessages(
                collect($e->errors())->mapWithKeys(fn ($messages, $key) => ["data.{$key}" => $messages])->all(),
            );
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Invitación enviada';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
