<?php

namespace App\Filament\Resources\Insurers\Tables;

use App\Domain\Directory\Enums\InsurerStatus;
use App\Domain\Directory\Enums\InsurerType;
use App\Filament\Actions\ToggleStatusAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InsurersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('country:id,name')->withCount(['doctors', 'facilities']))
            ->columns([
                TextColumn::make('name')->label('Aseguradora')->searchable()->sortable(),
                TextColumn::make('country.name')->label('País')->sortable(),
                TextColumn::make('type')->label('Tipo')->badge()->color('gray'),
                TextColumn::make('doctors_count')->label('Médicos')->numeric()->sortable(),
                TextColumn::make('facilities_count')->label('Establecimientos')->numeric()->sortable(),
                TextColumn::make('slug')->label('Slug')->color('gray')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->label('Estado')->badge(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('country_id')->label('País')->relationship('country', 'name'),
                SelectFilter::make('type')->label('Tipo')->options(InsurerType::class),
                SelectFilter::make('status')->label('Estado')->options(InsurerStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
                ToggleStatusAction::make(),
            ])
            ->emptyStateHeading('Sin aseguradoras')
            ->emptyStateDescription('Da de alta las aseguradoras de cada país: BMI, Pan-American Life, ARS Humano, SeNaSa…');
    }
}
