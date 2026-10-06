<?php

namespace App\Filament\Resources\Facilities\Tables;

use App\Domain\Directory\Enums\FacilitySector;
use App\Domain\Directory\Enums\FacilityStatus;
use App\Domain\Directory\Enums\FacilityType;
use App\Domain\Geo\Support\Normalize;
use App\Filament\Resources\Facilities\Actions\FacilityActions;
use App\Models\Facility;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class FacilitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['country:id,name', 'network:id,name,short_name'])
                ->withCount('locations')
                // Médicos derivados de las sedes (DATABASE.md §9.11), sin repetir al que atiende en dos.
                ->addSelect(['doctors_count' => DB::table('doctor_locations')
                    ->join('locations', 'locations.id', '=', 'doctor_locations.location_id')
                    ->whereColumn('locations.facility_id', 'facilities.id')
                    ->selectRaw('count(distinct doctor_locations.doctor_id)')]))
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->columns([
                ImageColumn::make('logo_path')
                    ->label('')
                    ->disk(fn () => config('meemedico.media_disk'))
                    ->circular()
                    ->imageSize(36),
                TextColumn::make('name')
                    ->label('Establecimiento')
                    ->description(fn (Facility $record) => $record->network?->short_name ?? $record->network?->name)
                    // Sin acentos: "clinica biblica" encuentra "Clínica Bíblica".
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereRaw(
                        'immutable_unaccent(lower(facilities.name)) like ?',
                        ['%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], Normalize::text($search)).'%'],
                    ))
                    ->sortable(),
                TextColumn::make('type')->label('Tipo')->badge()->color('gray'),
                TextColumn::make('sector')->label('Sector'),
                TextColumn::make('country.name')->label('País'),
                TextColumn::make('locations_count')->label('Sedes')->numeric()->sortable(),
                TextColumn::make('doctors_count')->label('Médicos')->numeric()->sortable(),
                TextColumn::make('status')->label('Estado')->badge(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('country_id')->label('País')->relationship('country', 'name'),
                SelectFilter::make('type')->label('Tipo')->options(FacilityType::class),
                SelectFilter::make('sector')->label('Sector')->options(FacilitySector::class),
                SelectFilter::make('network_id')->label('Red')->relationship('network', 'name')->searchable(),
                SelectFilter::make('status')->label('Estado')->options(FacilityStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    FacilityActions::bulkPublish(),
                    FacilityActions::bulkUnpublish(),
                ]),
            ])
            ->emptyStateHeading('Sin establecimientos')
            ->emptyStateDescription('Crea un hospital, clínica o centro médico y añádele sus sedes y contactos.');
    }
}
