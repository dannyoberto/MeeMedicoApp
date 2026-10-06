<?php

namespace App\Filament\Resources\Facilities\RelationManagers;

use App\Domain\Directory\Actions\DoctorLocationsAction;
use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Enums\LocationStatus;
use App\Domain\Directory\Enums\LocationType;
use App\Domain\Directory\Support\DoctorSearch;
use App\Filament\Resources\Doctors\DoctorResource;
use App\Filament\Resources\Facilities\RelationManagers\Concerns\ChangesFacilityAggregate;
use App\Models\Doctor;
use App\Models\Location;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

use function Illuminate\Support\enum_value;

/**
 * Médicos del establecimiento: los que atienden en alguna de sus sedes (DATABASE.md §9.11).
 * No es una relación guardada sino derivada, así que la tabla usa su propia consulta
 * (Facility::doctors). Asociar un médico es vincularlo a una sede, con DoctorLocationsAction:
 * la misma regla que desde su ficha.
 */
class DoctorsRelationManager extends RelationManager
{
    use ChangesFacilityAggregate;

    /** Relación real del dueño, para lo que Filament resuelve internamente. Los registros salen de query(). */
    protected static string $relationship = 'locations';

    protected static ?string $title = 'Médicos';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('doctors.view') ?? false;
    }

    protected function canLinkDoctors(): bool
    {
        return auth()->user()?->can('doctors.update') ?? false;
    }

    public function table(Table $table): Table
    {
        $facilityId = $this->facility()->getKey();

        return $table
            ->query(fn () => $this->facility()->doctors()
                ->where('status', '<>', DoctorStatus::Merged)
                ->with([
                    'specialties' => fn ($q) => $q->wherePivot('is_primary', true),
                    'locations' => fn ($q) => $q->where('locations.facility_id', $facilityId),
                ]))
            ->recordTitleAttribute('last_name')
            ->defaultSort('last_name')
            ->columns([
                TextColumn::make('last_name')->label('Médico')
                    ->formatStateUsing(fn (Doctor $record) => DoctorResource::displayName($record))
                    ->description(fn (Doctor $record) => $record->license_number ? "Lic. {$record->license_number}" : null)
                    ->searchable(query: fn (Builder $query, string $search) => DoctorSearch::apply($query, $search))
                    ->sortable(['last_name', 'first_name']),
                TextColumn::make('specialties.name')->label('Especialidad')->placeholder('—'),
                TextColumn::make('sedes')
                    ->label('Sede y modalidad')
                    ->state(fn (Doctor $record) => $record->locations
                        ->map(fn (Location $l) => $l->address.' · '.LocationType::from($l->pivot->location_type)->getLabel())
                        ->all())
                    ->listWithLineBreaks(),
                TextColumn::make('status')->label('Publicación')->badge(),
            ])
            ->recordUrl(fn (Doctor $record) => DoctorResource::getUrl('edit', ['record' => $record]))
            ->headerActions([
                Action::make('attachDoctor')
                    ->label('Asociar médico')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->modalDescription('El médico queda vinculado a una sede del establecimiento, como si se hiciera desde su ficha.')
                    ->schema([
                        Select::make('doctor_id')
                            ->label('Médico')
                            ->helperText('Busca por nombre o colegiado, sin importar acentos. Solo médicos del mismo país.')
                            ->getSearchResultsUsing(fn (string $search) => DoctorSearch::apply(Doctor::query(), $search)
                                ->where('country_id', $this->facility()->country_id)
                                ->where('status', '<>', DoctorStatus::Merged)
                                ->orderBy('last_name')->limit(20)->get()
                                ->mapWithKeys(fn (Doctor $d) => [$d->id => self::doctorOption($d)])->all())
                            ->getOptionLabelUsing(fn ($value) => ($d = Doctor::find($value)) ? self::doctorOption($d) : null)
                            ->searchable()
                            ->required(),
                        Select::make('location_id')
                            ->label('Sede')
                            ->options(fn () => $this->facility()->locations()->where('status', LocationStatus::Active)->pluck('address', 'id'))
                            ->default(fn () => ($ids = $this->facility()->locations()->where('status', LocationStatus::Active)->pluck('id'))->count() === 1 ? $ids->first() : null)
                            ->required(),
                        Grid::make(2)->schema([
                            Select::make('location_type')->label('Modalidad')->options(LocationType::class)
                                ->default(fn () => $this->facility()->type->defaultLocationType())
                                ->helperText('Cómo atiende ahí: consultorio propio, clínica, hospital.')
                                ->required(),
                            Toggle::make('primary')->label('Ubicación principal del médico')
                                ->helperText('Decide en qué listado de ciudad aparece.')->inline(false),
                        ]),
                    ])
                    ->authorize(fn () => $this->canLinkDoctors())
                    ->action(fn (array $data, Action $action) => $this->changeAggregate($action,
                        fn () => app(DoctorLocationsAction::class)->attachExisting(
                            Doctor::findOrFail($data['doctor_id']),
                            $this->facility()->locations()->findOrFail($data['location_id']),
                            LocationType::from(enum_value($data['location_type'])),
                            auth()->user(),
                            (bool) ($data['primary'] ?? false),
                        ),
                        'Médico asociado')),
            ])
            ->recordActions([
                Action::make('detachDoctor')
                    ->label('Desvincular')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Se quitan de su ficha las sedes de este establecimiento. Si es una ficha publicada y no tiene otra ubicación, no se podrá: primero añade otra o despublícala.')
                    ->authorize(fn () => $this->canLinkDoctors())
                    ->action(fn (Doctor $record, Action $action) => $this->changeAggregate($action,
                        // Todo o nada: si una sede no se puede quitar, no se quita ninguna.
                        fn () => DB::transaction(fn () => $record->locations()
                            ->where('locations.facility_id', $this->facility()->getKey())->get()
                            ->each(fn (Location $l) => app(DoctorLocationsAction::class)->detach($record, $l, auth()->user()))),
                        'Médico desvinculado')),
            ])
            ->emptyStateHeading('Ningún médico atiende aquí')
            ->emptyStateDescription('Asocia médicos a una de sus sedes.');
    }

    private static function doctorOption(Doctor $doctor): string
    {
        return DoctorResource::displayName($doctor).($doctor->license_number ? " · Lic. {$doctor->license_number}" : '');
    }
}
