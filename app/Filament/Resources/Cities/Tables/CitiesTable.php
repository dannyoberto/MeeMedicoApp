<?php

namespace App\Filament\Resources\Cities\Tables;

use App\Domain\Geo\Enums\CityStatus;
use App\Filament\Actions\ToggleStatusAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('region.name')
                    ->label('Región')
                    ->sortable(),
                TextColumn::make('country.name')
                    ->label('País')
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->color('gray')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('aliases_count')
                    ->label('Alias')
                    ->counts('aliases')
                    ->numeric(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('country_id')
                    ->label('País')
                    ->relationship('country', 'name'),
                SelectFilter::make('region_id')
                    ->label('Región')
                    ->relationship('region', 'name')
                    ->searchable(),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(CityStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
                ToggleStatusAction::make(),
            ]);
    }
}
