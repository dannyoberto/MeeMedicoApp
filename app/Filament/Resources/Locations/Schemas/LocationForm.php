<?php

namespace App\Filament\Resources\Locations\Schemas;

use App\Domain\Geo\Enums\CityStatus;
use App\Models\City;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * País y región no se eligen: se derivan de la ciudad (SaveLocationAction), y la FK
 * compuesta los exige coherentes. Sin índice ni mapa de coordenadas hasta Fase 3.
 */
class LocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        Select::make('city_id')
                            ->label('Ciudad')
                            ->options(fn () => City::where('status', CityStatus::Active)
                                ->with('country:id,name')->orderBy('name')->get()
                                ->mapWithKeys(fn (City $c) => [$c->id => "{$c->name} ({$c->country->name})"]))
                            ->searchable()
                            ->required(),
                        TextInput::make('name')->label('Nombre del lugar')->placeholder('Torre Médica Momentum, piso 4')->maxLength(200),
                        TextInput::make('address')->label('Dirección')->required()->maxLength(255),
                        TextInput::make('address_2')->label('Complemento')->maxLength(255),
                        Grid::make(3)->columnSpanFull()->schema([
                            TextInput::make('postal_code')->label('Código postal')->maxLength(30),
                            TextInput::make('latitude')->label('Latitud')->numeric()->minValue(-90)->maxValue(90),
                            TextInput::make('longitude')->label('Longitud')->numeric()->minValue(-180)->maxValue(180),
                        ]),
                    ]),
            ]);
    }
}
