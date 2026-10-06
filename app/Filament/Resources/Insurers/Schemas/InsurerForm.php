<?php

namespace App\Filament\Resources\Insurers\Schemas;

use App\Domain\Directory\Enums\InsurerType;
use App\Filament\Forms\FormLayout;
use App\Filament\Forms\SlugFields;
use App\Models\Insurer;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

/**
 * Alta en modal y edición en su página. El slug es la base de las futuras páginas de
 * filtro ("cardiólogos que aceptan BMI"): se propone desde el nombre y luego solo cambia
 * con su 301.
 */
class InsurerForm
{
    public static function configure(Schema $schema): Schema
    {
        return FormLayout::configure($schema, [
            Select::make('country_id')
                ->label('País')
                ->relationship('country', 'name')
                ->required()
                ->live()
                // Sus médicos y convenios son de este país.
                ->disabled(fn (string $operation) => $operation !== 'create'),
            Select::make('type')
                ->label('Tipo')
                ->options(InsurerType::class)
                ->helperText('Pública: seguridad social que funciona como aseguradora (SeNaSa).')
                ->default(InsurerType::Private)
                ->required(),
            ...SlugFields::make(Insurer::class, nameMaxLength: 200),
        ]);
    }
}
