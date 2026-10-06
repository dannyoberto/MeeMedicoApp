<?php

namespace App\Filament\Resources\Facilities\RelationManagers;

use App\Domain\Directory\Actions\FacilityLocationsAction;
use App\Domain\Directory\Enums\LocationStatus;
use App\Domain\Directory\Support\LocationSearch;
use App\Filament\Resources\Facilities\RelationManagers\Concerns\ChangesFacilityAggregate;
use App\Filament\Resources\Locations\LocationResource;
use App\Filament\Resources\Locations\Schemas\LocationForm;
use App\Models\Location;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sedes del establecimiento (DATABASE.md §9.11). Una dirección pertenece como máximo a un
 * establecimiento: traerla de otro es una acción aparte y explícita ("Mover"). Quitar una
 * sede no la borra ni desvincula a sus médicos.
 */
class LocationsRelationManager extends RelationManager
{
    use ChangesFacilityAggregate;

    protected static string $relationship = 'locations';

    protected static ?string $title = 'Sedes';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('address')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('city:id,name')->withCount('doctors'))
            ->defaultSort('address')
            ->columns([
                TextColumn::make('address')->label('Dirección')
                    ->description(fn (Location $record) => $record->name),
                TextColumn::make('city.name')->label('Ciudad'),
                TextColumn::make('doctors_count')->label('Médicos')->numeric(),
                TextColumn::make('status')->label('Estado')->badge(),
            ])
            ->headerActions([
                Action::make('createSede')
                    ->label('Nueva sede')
                    ->icon(Heroicon::OutlinedPlus)
                    ->schema([Grid::make(2)->schema(LocationForm::fields(fn () => $this->facility()->country_id))])
                    ->authorize(fn () => $this->canChangeAggregate())
                    ->action(fn (array $data, Action $action) => $this->changeAggregate($action,
                        fn () => app(FacilityLocationsAction::class)->create($this->facility(), $data, auth()->user()),
                        'Sede añadida')),
                Action::make('assignSede')
                    ->label('Usar una dirección existente')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->color('gray')
                    ->modalDescription('Para direcciones que ya existen sueltas, por ejemplo las que creó el importador. Sus médicos pasan a contar como médicos de este establecimiento.')
                    ->schema([
                        $this->locationSelect(fn (Builder $q) => $q->whereNull('facility_id'),
                            'Solo direcciones activas de este país que no son sede de ningún establecimiento.'),
                    ])
                    ->authorize(fn () => $this->canChangeAggregate())
                    ->action(fn (array $data, Action $action) => $this->changeAggregate($action,
                        fn () => app(FacilityLocationsAction::class)->assign($this->facility(), Location::findOrFail($data['location_id']), auth()->user()),
                        'Sede asignada')),
                Action::make('moveSede')
                    ->label('Traer de otro establecimiento')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('La sede deja de pertenecer a su establecimiento actual. Los contactos que ese establecimiento tenía en ella se quedan con él, como contactos generales.')
                    ->schema([
                        $this->locationSelect(fn (Builder $q) => $q->whereNotNull('facility_id')->where('facility_id', '<>', $this->facility()->getKey()),
                            'Sedes de otros establecimientos de este país.'),
                    ])
                    ->authorize(fn () => $this->canChangeAggregate())
                    ->action(fn (array $data, Action $action) => $this->changeAggregate($action,
                        fn () => app(FacilityLocationsAction::class)->move($this->facility(), Location::findOrFail($data['location_id']), auth()->user()),
                        'Sede movida a este establecimiento')),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('editLocation')
                        ->label('Editar dirección')
                        ->icon(Heroicon::OutlinedPencilSquare)
                        ->url(fn (Location $record) => LocationResource::getUrl('edit', ['record' => $record]))
                        ->visible(fn (Location $record) => auth()->user()?->can('update', $record) ?? false),
                    Action::make('detachSede')
                        ->label('Quitar sede')
                        ->icon(Heroicon::OutlinedXMark)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('La dirección deja de ser sede de este establecimiento; no se borra y sus médicos la conservan.')
                        ->authorize(fn () => $this->canChangeAggregate())
                        ->action(fn (Location $record, Action $action) => $this->changeAggregate($action,
                            fn () => app(FacilityLocationsAction::class)->detach($this->facility(), $record, auth()->user()),
                            'Sede quitada')),
                ]),
            ])
            ->emptyStateHeading('Sin sedes')
            ->emptyStateDescription('Hace falta al menos una sede activa para activarlo.');
    }

    /**
     * Buscador de direcciones del país del establecimiento, sin acentos.
     *
     * @param  Closure(Builder<Location>): Builder<Location>  $scope
     */
    private function locationSelect(Closure $scope, string $help): Select
    {
        $options = fn (Builder $query) => $query->with(['city:id,name', 'facility:id,name'])->get()
            ->mapWithKeys(fn (Location $l) => [$l->id => trim("{$l->name} · {$l->address} ({$l->city->name})", ' ·')
                .($l->facility ? " — sede de {$l->facility->name}" : '')])
            ->all();

        return Select::make('location_id')
            ->label('Dirección')
            ->helperText($help)
            ->getSearchResultsUsing(fn (string $search) => $options($scope(LocationSearch::apply(Location::query(), $search))
                ->where('country_id', $this->facility()->country_id)
                ->where('status', LocationStatus::Active)
                ->orderBy('name')->orderBy('address')
                ->limit(20)))
            // Sin esto, el select mostraría el identificador tras un error de validación.
            ->getOptionLabelUsing(fn ($value) => $options(Location::whereKey($value))[$value] ?? null)
            ->searchable()
            ->required();
    }
}
