<?php

namespace App\Filament\Resources\Facilities\Pages;

use App\Domain\Directory\Actions\UpdateFacilityAction;
use App\Filament\Actions\ChangeSlugAction;
use App\Filament\Resources\Facilities\Actions\FacilityActions;
use App\Filament\Resources\Facilities\FacilityResource;
use App\Models\Facility;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;

/**
 * @property Facility $record
 */
class EditFacility extends EditRecord
{
    protected static string $resource = FacilityResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->record->name;
    }

    public function getSubheading(): ?string
    {
        return collect([
            $this->record->type?->getLabel(),
            $this->record->sector?->getLabel(),
            $this->record->network?->name,
            $this->record->country?->name,
        ])->filter()->implode(' · ');
    }

    protected function getHeaderActions(): array
    {
        return [
            FacilityActions::publish(),
            FacilityActions::unpublish(),
            ChangeSlugAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(UpdateFacilityAction::class)->execute($record, $data, auth()->user());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(
                collect($e->errors())->mapWithKeys(fn ($m, $key) => ["data.{$key}" => $m])->all(),
            );
        }
    }

    /**
     * Las pestañas avisan tras cada cambio de sedes o contactos: se recarga el
     * establecimiento para que la lista de requisitos refleje el estado real.
     */
    #[On('facility-aggregate-changed')]
    public function refreshAggregate(): void
    {
        $this->record->refresh();
    }
}
