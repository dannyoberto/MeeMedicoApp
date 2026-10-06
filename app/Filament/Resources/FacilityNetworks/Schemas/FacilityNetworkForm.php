<?php

namespace App\Filament\Resources\FacilityNetworks\Schemas;

use App\Domain\Directory\Enums\FacilitySector;
use App\Filament\Forms\FormLayout;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

/**
 * Solo en modal (ManageFacilityNetworks). Sin slug: no hay página de red.
 */
class FacilityNetworkForm
{
    public static function configure(Schema $schema): Schema
    {
        return FormLayout::configure($schema, [
            Select::make('country_id')
                ->label('País')
                ->relationship('country', 'name')
                ->required()
                ->live()
                // Sus establecimientos la referencian con FK compuesta (país incluido).
                ->disabled(fn (string $operation) => $operation !== 'create')
                ->columnSpanFull(),
            TextInput::make('name')
                ->label('Nombre')
                ->placeholder('Caja Costarricense de Seguro Social')
                ->required()
                ->maxLength(200)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('country_id', $get('country_id'))),
            TextInput::make('short_name')
                ->label('Sigla')
                ->placeholder('CCSS')
                ->maxLength(30),
            Select::make('sector')
                ->label('Sector')
                ->options(FacilitySector::class)
                ->helperText('Se propone a sus establecimientos al crearlos.')
                ->required(),
        ]);
    }
}
