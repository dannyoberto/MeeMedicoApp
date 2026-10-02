<?php

namespace App\Filament\Resources\Regions\Schemas;

use App\Filament\Forms\FormLayout;
use App\Filament\Forms\SlugFields;
use App\Models\Region;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

/**
 * Solo en modal (ManageRegions): el país a todo el ancho y debajo nombre y slug.
 */
class RegionForm
{
    public static function configure(Schema $schema): Schema
    {
        return FormLayout::configure($schema, [
            Select::make('country_id')
                ->label('País')
                ->relationship('country', 'name')
                ->required()
                ->live()
                // Cambiar de país rompería la FK compuesta de sus ciudades.
                ->disabled(fn (string $operation) => $operation !== 'create')
                ->columnSpanFull(),
            ...SlugFields::make(Region::class),
        ]);
    }
}
