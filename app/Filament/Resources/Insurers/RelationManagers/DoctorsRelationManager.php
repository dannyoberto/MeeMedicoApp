<?php

namespace App\Filament\Resources\Insurers\RelationManagers;

use App\Domain\Directory\Actions\DoctorInsurersAction;
use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Support\DoctorSearch;
use App\Filament\Resources\Doctors\DoctorResource;
use App\Filament\Support\DomainAction;
use App\Models\Doctor;
use App\Models\Insurer;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Médicos que atienden por esta aseguradora. Vincular y quitar pasa por
 * DoctorInsurersAction, la misma regla que desde la ficha del médico. Para muchos a la
 * vez: la acción en lote "Asignar aseguradora" del listado de Médicos.
 */
class DoctorsRelationManager extends RelationManager
{
    protected static string $relationship = 'doctors';

    protected static ?string $title = 'Médicos';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('doctors.view') ?? false;
    }

    private function insurer(): Insurer
    {
        /** @var Insurer */
        return $this->getOwnerRecord();
    }

    private function canLink(): bool
    {
        return auth()->user()?->can('doctors.update') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('last_name')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['specialties' => fn ($q) => $q->wherePivot('is_primary', true)]))
            ->defaultSort('last_name')
            ->columns([
                TextColumn::make('last_name')->label('Médico')
                    ->formatStateUsing(fn (Doctor $record) => DoctorResource::displayName($record))
                    ->description(fn (Doctor $record) => $record->license_number ? "Lic. {$record->license_number}" : null)
                    ->searchable(query: fn (Builder $query, string $search) => DoctorSearch::apply($query, $search))
                    ->sortable(['last_name', 'first_name']),
                TextColumn::make('specialties.name')->label('Especialidad')->placeholder('—'),
                TextColumn::make('status')->label('Publicación')->badge(),
                TextColumn::make('pivot.created_at')->label('Desde')->date(),
            ])
            ->recordUrl(fn (Doctor $record) => DoctorResource::getUrl('edit', ['record' => $record]))
            ->headerActions([
                Action::make('attachDoctor')
                    ->label('Añadir médico')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->modalDescription('Para muchos médicos a la vez, usa "Asignar aseguradora" en el listado de Médicos.')
                    ->schema([
                        Select::make('doctor_id')
                            ->label('Médico')
                            ->helperText('Busca por nombre o colegiado, sin importar acentos. Solo médicos del país de la aseguradora.')
                            ->getSearchResultsUsing(fn (string $search) => DoctorSearch::apply(Doctor::query(), $search)
                                ->where('country_id', $this->insurer()->country_id)
                                ->where('status', '<>', DoctorStatus::Merged)
                                ->whereDoesntHave('insurers', fn ($q) => $q->whereKey($this->insurer()->getKey()))
                                ->orderBy('last_name')->limit(20)->get()
                                ->mapWithKeys(fn (Doctor $d) => [$d->id => self::doctorOption($d)])->all())
                            ->getOptionLabelUsing(fn ($value) => ($d = Doctor::find($value)) ? self::doctorOption($d) : null)
                            ->searchable()
                            ->required(),
                    ])
                    ->authorize(fn () => $this->canLink())
                    ->action(function (array $data, Action $action) {
                        DomainAction::run($action, fn () => app(DoctorInsurersAction::class)->attach(Doctor::findOrFail($data['doctor_id']), $this->insurer(), auth()->user()));
                        Notification::make()->success()->title('Médico añadido')->send();
                    }),
            ])
            ->recordActions([
                Action::make('detachDoctor')
                    ->label('Quitar')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn () => $this->canLink())
                    ->action(function (Doctor $record, Action $action) {
                        DomainAction::run($action, fn () => app(DoctorInsurersAction::class)->detach($record, $this->insurer(), auth()->user()));
                        Notification::make()->success()->title('Médico quitado')->send();
                    }),
            ])
            ->emptyStateHeading('Ningún médico registrado con esta aseguradora');
    }

    private static function doctorOption(Doctor $doctor): string
    {
        return DoctorResource::displayName($doctor).($doctor->license_number ? " · Lic. {$doctor->license_number}" : '');
    }
}
