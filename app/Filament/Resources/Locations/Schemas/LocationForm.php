<?php

namespace App\Filament\Resources\Locations\Schemas;

use App\Domain\Geo\Enums\CityStatus;
use App\Models\City;
use App\Models\Country;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * País y región no se guardan desde aquí: se derivan de la ciudad (SaveLocationAction), y la FK
 * compuesta los exige coherentes. El selector de país solo filtra las ciudades.
 * Sin índice ni mapa de coordenadas hasta Fase 3.
 */
class LocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema(self::fields()),
            ]);
    }

    /**
     * Campos de la ubicación, compartidos con el alta desde la ficha del médico.
     *
     * @param  (Closure(): ?string)|null  $defaultCountry
     * @return array<int, mixed>
     */
    public static function fields(?Closure $defaultCountry = null): array
    {
        return [
            Select::make('country_id')
                ->label('País')
                ->options(fn () => Country::orderBy('name')->pluck('name', 'id'))
                ->default($defaultCountry)
                ->placeholder('Todos')
                ->helperText('Filtra las ciudades. El país se toma de la ciudad.')
                ->live()
                ->dehydrated(false)
                ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                    if ($state && ($city = City::find($get('city_id'))) && $city->country_id !== $state) {
                        $set('city_id', null);
                    }
                }),
            Select::make('city_id')
                ->label('Ciudad')
                ->options(fn (Get $get) => City::where('status', CityStatus::Active)
                    ->when($get('country_id'), fn ($q, $country) => $q->where('country_id', $country))
                    ->with('country:id,name')->orderBy('name')->get()
                    ->mapWithKeys(fn (City $c) => [$c->id => $get('country_id') ? $c->name : "{$c->name} ({$c->country->name})"]))
                ->searchable()
                ->live()
                ->afterStateUpdated(fn (Set $set, ?string $state) => $state
                    && $set('country_id', City::whereKey($state)->value('country_id')))
                ->required(),
            TextInput::make('name')->label('Nombre del lugar')
                ->placeholder('Hospital Clínica Bíblica, Torre Médica Momentum piso 4…')->maxLength(200),
            TextInput::make('address')->label('Dirección')->required()->maxLength(255),
            TextInput::make('address_2')->label('Complemento')->maxLength(255)->columnSpanFull(),
            Grid::make(3)->columnSpanFull()->schema([
                TextInput::make('postal_code')->label('Código postal')->maxLength(30),
                TextInput::make('latitude')->label('Latitud')->numeric()->minValue(-90)->maxValue(90),
                TextInput::make('longitude')->label('Longitud')->numeric()->minValue(-180)->maxValue(180),
            ]),
        ];
    }
}
