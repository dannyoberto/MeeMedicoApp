<?php

namespace App\Filament\Resources\Locations\Tables;

use App\Domain\Directory\Enums\LocationStatus;
use App\Domain\Directory\Enums\LocationType;
use App\Domain\Directory\Support\LocationSearch;
use App\Filament\Actions\ToggleStatusAction;
use App\Filament\Resources\Facilities\FacilityResource;
use App\Models\Location;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * El tipo no es de la ubicación sino de cada médico que atiende en ella (doctor_locations.location_type,
 * DATABASE.md §9.6). La columna "Tipos" lo resume: dos tipos distintos en el mismo lugar es una señal
 * para revisar, no un error.
 */
class LocationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['city:id,name', 'country:id,name', 'facility:id,name'])
                ->withCount(['doctors', ...self::typeCounts()]))
            ->splitSearchTerms(false)
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->columns([
                TextColumn::make('address')
                    ->label('Dirección')
                    ->description(fn (Location $record) => $record->name)
                    ->searchable(query: fn (Builder $query, string $search) => LocationSearch::apply($query, $search)),
                TextColumn::make('facility.name')
                    ->label('Establecimiento')
                    ->url(fn (Location $record) => $record->facility_id ? FacilityResource::getUrl('edit', ['record' => $record->facility_id]) : null)
                    ->placeholder('—'),
                TextColumn::make('city.name')->label('Ciudad')->sortable(),
                TextColumn::make('country.name')->label('País'),
                TextColumn::make('types')
                    ->label('Tipos')
                    ->state(fn (Location $record) => collect(LocationType::cases())
                        ->filter(fn (LocationType $type) => $record->{self::typeCountAlias($type)} > 0)
                        ->map(fn (LocationType $type) => $type->getLabel().' ×'.$record->{self::typeCountAlias($type)})
                        ->values()->all())
                    ->badge()
                    ->color('gray')
                    ->placeholder('Sin médicos'),
                TextColumn::make('doctors_count')
                    ->label('Médicos')
                    ->badge()
                    ->color(fn (int $state) => $state > 1 ? 'warning' : 'gray')
                    ->sortable(),
                TextColumn::make('status')->label('Estado')->badge(),
            ])
            ->defaultSort('address')
            ->filters([
                SelectFilter::make('country_id')->label('País')->relationship('country', 'name'),
                SelectFilter::make('city_id')->label('Ciudad')->relationship('city', 'name')->searchable(),
                SelectFilter::make('facility_id')->label('Establecimiento')->relationship('facility', 'name')->searchable(),
                SelectFilter::make('location_type')
                    ->label('Tipo')
                    ->options(LocationType::class)
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null,
                        fn (Builder $q, $type) => $q->whereHas('doctors',
                            fn (Builder $d) => $d->where('doctor_locations.location_type', $type)))),
                SelectFilter::make('status')->label('Estado')->options(LocationStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
                ToggleStatusAction::make(),
            ]);
    }

    /**
     * @return array<string, \Closure>
     */
    private static function typeCounts(): array
    {
        return collect(LocationType::cases())->mapWithKeys(fn (LocationType $type) => [
            'doctors as '.self::typeCountAlias($type) => fn (Builder $q) => $q->where('doctor_locations.location_type', $type->value),
        ])->all();
    }

    private static function typeCountAlias(LocationType $type): string
    {
        return "{$type->value}_doctors_count";
    }
}
