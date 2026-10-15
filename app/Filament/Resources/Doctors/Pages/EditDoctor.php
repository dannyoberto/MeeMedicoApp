<?php

namespace App\Filament\Resources\Doctors\Pages;

use App\Domain\Directory\Actions\UpdateDoctorAction;
use App\Filament\Actions\ChangeSlugAction;
use App\Filament\Resources\Doctors\Actions\DoctorActions;
use App\Filament\Resources\Doctors\DoctorResource;
use App\Models\Doctor;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;

/**
 * @property Doctor $record
 */
class EditDoctor extends EditRecord
{
    protected static string $resource = DoctorResource::class;

    /**
     * Debajo del formulario van las pestañas del agregado, que guardan al instante: sin
     * barra fija, "Guardar cambios" queda a media página y parece que guarda también eso.
     */
    public static bool $formActionsAreSticky = true;

    public function getTitle(): string|Htmlable
    {
        return DoctorResource::displayName($this->record);
    }

    /**
     * Contexto bajo el nombre, como en EditFacility. Se consulta en cada render para que
     * refleje los cambios hechos en las pestañas de especialidades y ubicaciones.
     */
    public function getSubheading(): ?string
    {
        return collect([
            $this->record->specialties()->wherePivot('is_primary', true)->value('specialties.name'),
            $this->record->locations()->wherePivot('is_primary', true)->first()?->city?->name,
            $this->record->country?->name,
            $this->record->license_number ? "Lic. {$this->record->license_number}" : null,
        ])->filter()->implode(' · ') ?: null;
    }

    protected function getHeaderActions(): array
    {
        return [
            DoctorActions::publish(),
            DoctorActions::unpublish(),
            ActionGroup::make([
                DoctorActions::verify(),
                DoctorActions::rejectVerification(),
                ChangeSlugAction::make(),
                DoctorActions::suspend(),
                DoctorActions::liftSuspension(),
            ])->label('Más')->button()->color('gray'),
        ];
    }

    /**
     * El perfil vive en doctor_profiles: se muestra en la misma pestaña.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, ...$this->record->profile?->only(['headline', 'bio', 'education', 'experience']) ?? []];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(UpdateDoctorAction::class)->execute($record, $data, auth()->user());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(
                collect($e->errors())->mapWithKeys(fn ($m, $key) => ["data.{$key}" => $m])->all(),
            );
        }
    }

    /**
     * Los gestores de relaciones avisan tras cada cambio del agregado: se recarga la
     * ficha para que la lista de requisitos y el subtítulo reflejen el estado real.
     */
    #[On('doctor-aggregate-changed')]
    public function refreshAggregate(): void
    {
        $this->record->refresh();
    }
}
