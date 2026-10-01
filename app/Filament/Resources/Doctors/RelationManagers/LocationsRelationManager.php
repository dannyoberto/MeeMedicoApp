<?php

namespace App\Filament\Resources\Doctors\RelationManagers;

use App\Domain\Directory\Actions\DoctorLocationsAction;
use App\Domain\Directory\Enums\LocationStatus;
use App\Domain\Directory\Enums\LocationType;
use App\Domain\Geo\Enums\CityStatus;
use App\Filament\Resources\Doctors\RelationManagers\Concerns\ChangesDoctorAggregate;
use App\Filament\Resources\Locations\LocationResource;
use App\Models\City;
use App\Models\Location;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

use function Illuminate\Support\enum_value;

/**
 * Ubicaciones del médico. Una ubicación puede compartirse (torre médica): quitarla
 * la desasocia de esta ficha, nunca la borra. La principal decide el listado de ciudad.
 */
class LocationsRelationManager extends RelationManager
{
    use ChangesDoctorAggregate;

    protected static string $relationship = 'locations';

    protected static ?string $title = 'Ubicaciones';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('address')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('city:id,name')->withCount('doctors'))
            ->defaultSort('doctor_locations.is_primary', 'desc')
            ->columns([
                TextColumn::make('address')->label('Dirección')
                    ->description(fn (Location $record) => $record->name),
                TextColumn::make('city.name')->label('Ciudad'),
                TextColumn::make('pivot.location_type')->label('Tipo')
                    ->formatStateUsing(fn (?string $state) => $state ? LocationType::from($state)->getLabel() : '—'),
                IconColumn::make('pivot.is_primary')->label('Principal')->boolean()
                    ->trueIcon(Heroicon::OutlinedStar)->falseIcon(Heroicon::OutlinedMinus)
                    ->trueColor('primary')->falseColor('gray'),
                TextColumn::make('doctors_count')->label('Médicos')
                    ->badge()->color(fn (int $state) => $state > 1 ? 'warning' : 'gray')
                    ->tooltip(fn (int $state) => $state > 1 ? 'Compartida: editarla cambia la ficha de todos' : null),
                TextColumn::make('status')->label('Estado')->badge(),
            ])
            ->headerActions([
                Action::make('attachNewLocation')
                    ->label('Nueva ubicación')
                    ->icon(Heroicon::OutlinedPlus)
                    ->schema([
                        Select::make('city_id')
                            ->label('Ciudad')
                            ->options(fn () => City::where('status', CityStatus::Active)
                                ->with('country:id,name')->orderBy('name')->get()
                                ->mapWithKeys(fn (City $c) => [$c->id => "{$c->name} ({$c->country->name})"]))
                            ->searchable()
                            ->required(),
                        TextInput::make('name')->label('Nombre del lugar')->placeholder('Torre Médica Momentum, piso 4')->maxLength(200),
                        TextInput::make('address')->label('Dirección')->required()->maxLength(255),
                        TextInput::make('address_2')->label('Complemento')->maxLength(255),
                        Grid::make(3)->schema([
                            TextInput::make('postal_code')->label('Código postal')->maxLength(30),
                            TextInput::make('latitude')->label('Latitud')->numeric()->minValue(-90)->maxValue(90),
                            TextInput::make('longitude')->label('Longitud')->numeric()->minValue(-180)->maxValue(180),
                        ]),
                        ...self::linkFields(),
                    ])
                    ->authorize(fn () => $this->canChangeAggregate())
                    ->action(fn (array $data, Action $action) => $this->changeAggregate($action,
                        fn () => app(DoctorLocationsAction::class)->attachNew(
                            $this->doctor(), $data, LocationType::from(enum_value($data['location_type'])), auth()->user(), (bool) ($data['primary'] ?? false)),
                        'Ubicación añadida')),
                Action::make('attachExistingLocation')
                    ->label('Usar una existente')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->color('gray')
                    ->modalDescription('Para consultorios compartidos: la misma dirección queda asociada a varios médicos.')
                    ->schema([
                        Select::make('location_id')
                            ->label('Ubicación')
                            ->getSearchResultsUsing(fn (string $search) => Location::where('status', LocationStatus::Active)
                                ->whereNotIn('id', $this->doctor()->locations()->pluck('locations.id'))
                                ->where(fn ($q) => $q->where('address', 'ilike', "%{$search}%")->orWhere('name', 'ilike', "%{$search}%"))
                                ->with('city:id,name')->limit(20)->get()
                                ->mapWithKeys(fn (Location $l) => [$l->id => trim("{$l->name} · {$l->address} ({$l->city->name})", ' ·')]))
                            ->searchable()
                            ->required(),
                        ...self::linkFields(),
                    ])
                    ->authorize(fn () => $this->canChangeAggregate())
                    ->action(fn (array $data, Action $action) => $this->changeAggregate($action,
                        fn () => app(DoctorLocationsAction::class)->attachExisting(
                            $this->doctor(), Location::findOrFail($data['location_id']), LocationType::from(enum_value($data['location_type'])), auth()->user(), (bool) ($data['primary'] ?? false)),
                        'Ubicación asociada')),
            ])
            ->recordActions([
                Action::make('makePrimary')
                    ->label('Hacer principal')
                    ->icon(Heroicon::OutlinedStar)
                    ->color('gray')
                    ->visible(fn (Location $record) => ! $record->pivot->is_primary)
                    ->authorize(fn () => $this->canChangeAggregate())
                    ->action(fn (Location $record, Action $action) => $this->changeAggregate($action,
                        fn () => app(DoctorLocationsAction::class)->makePrimary($this->doctor(), $record, auth()->user()),
                        'Ubicación principal actualizada')),
                ActionGroup::make([
                    Action::make('changeType')
                        ->label('Cambiar tipo')
                        ->icon(Heroicon::OutlinedTag)
                        ->schema([Select::make('location_type')->label('Tipo')->options(LocationType::class)->required()
                            ->default(fn (Location $record) => $record->pivot->location_type)])
                        ->authorize(fn () => $this->canChangeAggregate())
                        ->action(fn (array $data, Location $record, Action $action) => $this->changeAggregate($action,
                            fn () => app(DoctorLocationsAction::class)->changeType($this->doctor(), $record, LocationType::from(enum_value($data['location_type'])), auth()->user()),
                            'Tipo actualizado')),
                    Action::make('editLocation')
                        ->label('Editar dirección')
                        ->icon(Heroicon::OutlinedPencilSquare)
                        ->url(fn (Location $record) => LocationResource::getUrl('edit', ['record' => $record]))
                        ->visible(fn (Location $record) => auth()->user()?->can('update', $record) ?? false),
                    Action::make('detachLocation')
                        ->label('Quitar de esta ficha')
                        ->icon(Heroicon::OutlinedXMark)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('La ubicación se desasocia de este médico; no se borra.')
                        ->authorize(fn () => $this->canChangeAggregate())
                        ->action(fn (Location $record, Action $action) => $this->changeAggregate($action,
                            fn () => app(DoctorLocationsAction::class)->detach($this->doctor(), $record, auth()->user()),
                            'Ubicación quitada')),
                ]),
            ])
            ->emptyStateHeading('Sin ubicaciones')
            ->emptyStateDescription('Hace falta al menos una, con ciudad, para publicar.');
    }

    /**
     * @return array<int, mixed>
     */
    private static function linkFields(): array
    {
        return [
            Grid::make(2)->schema([
                Select::make('location_type')->label('Tipo')->options(LocationType::class)
                    ->default(LocationType::Office)->required(),
                Toggle::make('primary')->label('Marcar como principal')
                    ->helperText('Decide en qué listado de ciudad aparece.')->inline(false),
            ]),
        ];
    }
}
