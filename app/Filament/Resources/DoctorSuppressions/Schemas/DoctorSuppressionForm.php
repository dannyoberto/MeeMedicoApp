<?php

namespace App\Filament\Resources\DoctorSuppressions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Solo creación. Las claves se guardan normalizadas (CreateSuppressionAction) para
 * que el importador las compare con la misma forma que usa en el matching.
 */
class DoctorSuppressionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Solicitud')
                    ->columns(2)
                    ->schema([
                        Select::make('country_id')
                            ->label('País')
                            ->relationship('country', 'name')
                            ->required()
                            ->live(),
                        DateTimePicker::make('requested_at')
                            ->label('Fecha de la solicitud')
                            ->default(now())
                            ->maxDate(now())
                            ->required(),
                        Textarea::make('reason')
                            ->label('Motivo')
                            ->helperText('Canal por el que llegó y lo que pidió la persona.')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
                Section::make('Claves de identificación')
                    ->description('Al menos una. Con cualquiera que coincida, el importador no volverá a crear la ficha.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('license_number')
                            ->label('Número de colegiado')
                            ->maxLength(100),
                        TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel()
                            ->helperText('Tal como lo dio la persona; se guarda en formato internacional.')
                            ->maxLength(30),
                        TextInput::make('email')
                            ->label('Correo')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('name')
                            ->label('Nombre completo')
                            ->helperText('Se guarda sin acentos ni títulos. Por sí solo es una clave débil: homónimos.')
                            ->maxLength(191),
                    ]),
            ]);
    }
}
