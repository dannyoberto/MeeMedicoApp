<?php

namespace App\Filament\Resources\Locations\Tables;

use App\Domain\Directory\Enums\LocationStatus;
use App\Filament\Actions\ToggleStatusAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LocationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['city:id,name', 'country:id,name'])->withCount('doctors'))
            ->columns([
                TextColumn::make('address')
                    ->label('Dirección')
                    ->description(fn ($record) => $record->name)
                    ->searchable(['address', 'name']),
                TextColumn::make('city.name')->label('Ciudad')->sortable(),
                TextColumn::make('country.name')->label('País'),
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
                SelectFilter::make('status')->label('Estado')->options(LocationStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
                ToggleStatusAction::make(),
            ]);
    }
}
