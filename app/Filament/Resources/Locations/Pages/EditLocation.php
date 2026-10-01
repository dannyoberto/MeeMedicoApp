<?php

namespace App\Filament\Resources\Locations\Pages;

use App\Domain\Directory\Actions\SaveLocationAction;
use App\Filament\Actions\ToggleStatusAction;
use App\Filament\Resources\Locations\LocationResource;
use App\Models\Location;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * @property Location $record
 */
class EditLocation extends EditRecord
{
    protected static string $resource = LocationResource::class;

    /**
     * Aviso de ubicación compartida (MODELO-DOMINIO.md §2.4): editarla cambia la ficha de todos.
     */
    public function getSubheading(): ?string
    {
        $doctors = $this->record->doctors()->count();

        return $doctors > 1
            ? "Compartida por {$doctors} médicos: los cambios se verán en todas sus fichas."
            : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            ToggleStatusAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(SaveLocationAction::class)->update($record, $data, auth()->user());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(
                collect($e->errors())->mapWithKeys(fn ($m, $key) => ["data.{$key}" => $m])->all(),
            );
        }
    }
}
