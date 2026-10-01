<?php

namespace App\Filament\Resources\Specialties\Schemas;

use App\Filament\Forms\SlugFields;
use App\Models\Specialty;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Sin parent_id: si las subespecialidades tendrán URL propia o serán un filtro es una
 * decisión de SEO pendiente (DATABASE.md §8.1). Se añade cuando se tome.
 */
class SpecialtyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        ...SlugFields::make(Specialty::class),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->helperText('Texto introductorio de la página de la especialidad.')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
