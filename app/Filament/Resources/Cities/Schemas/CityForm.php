<?php

namespace App\Filament\Resources\Cities\Schemas;

use App\Filament\Forms\FormLayout;
use App\Filament\Forms\SlugFields;
use App\Models\City;
use App\Models\Region;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Alta en modal desde la lista; edición en su página (por los alias). FormLayout pone
 * la tarjeta solo en la página.
 */
class CityForm
{
    public static function configure(Schema $schema): Schema
    {
        return FormLayout::configure($schema, [
            // País y región se fijan al crear: la FK compuesta
            // cities_region_country_fk exige que la región sea del país,
            // y cambiarlos movería la URL de la ciudad a otro país.
            Select::make('country_id')
                ->label('País')
                ->relationship('country', 'name')
                ->required()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('region_id', null))
                ->disabled(fn (string $operation) => $operation !== 'create'),
            Select::make('region_id')
                ->label('Región')
                ->options(fn (Get $get) => Region::query()
                    ->where('country_id', $get('country_id'))
                    ->orderBy('name')
                    ->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->disabled(fn (Get $get, string $operation) => $operation !== 'create' || blank($get('country_id')))
                ->helperText(fn (Get $get) => blank($get('country_id')) ? 'Elige primero el país.' : null),
            ...SlugFields::make(City::class),
        ]);
    }
}
