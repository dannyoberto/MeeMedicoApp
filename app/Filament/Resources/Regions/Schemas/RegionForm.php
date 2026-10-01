<?php

namespace App\Filament\Resources\Regions\Schemas;

use App\Filament\Forms\SlugFields;
use App\Models\Region;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RegionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        Select::make('country_id')
                            ->label('País')
                            ->relationship('country', 'name')
                            ->required()
                            ->live()
                            // Cambiar de país rompería la FK compuesta de sus ciudades.
                            ->disabled(fn (string $operation) => $operation !== 'create'),
                        ...SlugFields::make(Region::class),
                    ]),
            ]);
    }
}
