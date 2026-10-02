<?php

namespace App\Filament\Resources\Doctors\RelationManagers;

use App\Domain\Directory\Actions\DoctorLocationsAction;
use App\Domain\Directory\Enums\LocationStatus;
use App\Domain\Directory\Enums\LocationType;
use App\Domain\Directory\Support\LocationSearch;
use App\Filament\Resources\Doctors\RelationManagers\Concerns\ChangesDoctorAggregate;
use App\Filament\Resources\Locations\LocationResource;
use App\Filament\Resources\Locations\Schemas\LocationForm;
use App\Models\Location;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
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
                        Grid::make(2)->schema(LocationForm::fields(fn () => $this->doctor()->country_id)),
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
                            ->helperText('Busca por nombre del lugar o dirección, sin importar acentos. Primero salen las del país del médico.')
                            ->getSearchResultsUsing(fn (string $search) => self::locationOptions(LocationSearch::apply(Location::query(), $search)
                                ->where('status', LocationStatus::Active)
                                ->whereNotIn('id', $this->doctor()->locations()->pluck('locations.id'))
                                ->orderByRaw('country_id = ? desc', [$this->doctor()->country_id])
                                ->orderBy('name')->orderBy('address')
                                ->limit(20)))
                            // Sin esto, el select mostraría el identificador tras un error de validación.
                            ->getOptionLabelUsing(fn ($value) => self::locationOptions(Location::whereKey($value))[$value] ?? null)
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
     * @param  Builder<Location>  $query
     * @return array<string, string>
     */
    private static function locationOptions(Builder $query): array
    {
        return $query->with(['city:id,name', 'country:id,name'])->get()
            ->mapWithKeys(fn (Location $l) => [$l->id => trim("{$l->name} · {$l->address} ({$l->city->name}, {$l->country->name})", ' ·')])
            ->all();
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
