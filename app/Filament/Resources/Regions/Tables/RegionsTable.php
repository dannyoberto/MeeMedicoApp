<?php

namespace App\Filament\Resources\Regions\Tables;

use App\Domain\Geo\Enums\RegionStatus;
use App\Filament\Actions\ChangeSlugAction;
use App\Filament\Actions\ToggleStatusAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RegionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('country.name')
                    ->label('País')
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->color('gray')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cities_count')
                    ->label('Ciudades')
                    ->counts('cities')
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
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(RegionStatus::class),
            ])
            // Todo en modal: editar (país y slug bloqueados), cambiar slug con su 301 y dar de baja.
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    ChangeSlugAction::make(),
                    ToggleStatusAction::make(),
                ]),
            ]);
    }
}
