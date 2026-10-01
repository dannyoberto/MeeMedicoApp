<?php

namespace App\Filament\Resources\DoctorSuppressions\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DoctorSuppressionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('requested_at')
                    ->label('Solicitada')
                    ->date()
                    ->sortable(),
                TextColumn::make('country.name')
                    ->label('País'),
                TextColumn::make('license_number')
                    ->label('Colegiado')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('name_normalized')
                    ->label('Nombre (clave)')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('phone_normalized')
                    ->label('Teléfono')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('email_normalized')
                    ->label('Correo')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('creator.name')
                    ->label('Registrada por')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('requested_at', 'desc')
            ->filters([
                SelectFilter::make('country_id')
                    ->label('País')
                    ->relationship('country', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
