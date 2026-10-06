<?php

namespace App\Filament\Resources\FacilityNetworks\Tables;

use App\Domain\Directory\Enums\FacilityNetworkStatus;
use App\Domain\Directory\Enums\FacilitySector;
use App\Filament\Actions\ToggleStatusAction;
use App\Models\FacilityNetwork;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FacilityNetworksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->description(fn (FacilityNetwork $record) => $record->short_name)
                    ->searchable(['name', 'short_name'])
                    ->sortable(),
                TextColumn::make('country.name')->label('País')->sortable(),
                TextColumn::make('sector')->label('Sector')->badge()->color('gray'),
                TextColumn::make('facilities_count')->label('Establecimientos')->counts('facilities')->numeric(),
                TextColumn::make('status')->label('Estado')->badge(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('country_id')->label('País')->relationship('country', 'name'),
                SelectFilter::make('sector')->label('Sector')->options(FacilitySector::class),
                SelectFilter::make('status')->label('Estado')->options(FacilityNetworkStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
                ToggleStatusAction::make(),
            ])
            ->emptyStateHeading('Sin redes')
            ->emptyStateDescription('Registra las instituciones que operan establecimientos: CCSS, IGSS, SNS, IVSS o un grupo privado.');
    }
}
