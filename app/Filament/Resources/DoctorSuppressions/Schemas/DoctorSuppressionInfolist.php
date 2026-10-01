<?php

namespace App\Filament\Resources\DoctorSuppressions\Schemas;

use App\Domain\Directory\Support\SuppressionMatches;
use App\Models\DoctorSuppression;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DoctorSuppressionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Solicitud')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('country.name')->label('País'),
                        TextEntry::make('requested_at')->label('Solicitada')->dateTime(),
                        TextEntry::make('creator.name')->label('Registrada por')->placeholder('Sistema / consola'),
                        TextEntry::make('reason')->label('Motivo')->placeholder('—')->columnSpanFull(),
                    ]),
                Section::make('Claves (normalizadas)')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('license_number')->label('Colegiado')->placeholder('—')->copyable(),
                        TextEntry::make('phone_normalized')->label('Teléfono (E.164)')->placeholder('—')->copyable(),
                        TextEntry::make('email_normalized')->label('Correo')->placeholder('—')->copyable(),
                        TextEntry::make('name_normalized')->label('Nombre (clave)')->placeholder('—'),
                    ]),
                Section::make('Fichas existentes que coinciden')
                    ->description('La supresión impide que el importador las vuelva a crear, pero no despublica las existentes. Revísalas: despublicar llega con el módulo de Directorio.')
                    ->schema([
                        RepeatableEntry::make('matches')
                            ->hiddenLabel()
                            ->state(fn (DoctorSuppression $record) => SuppressionMatches::for($record)
                                ->map(fn ($doctor) => [
                                    'name' => trim("{$doctor->first_name} {$doctor->last_name}"),
                                    'license' => $doctor->license_number,
                                    'status' => $doctor->status,
                                ])->all())
                            ->columns(3)
                            ->schema([
                                TextEntry::make('name')->label('Médico'),
                                TextEntry::make('license')->label('Colegiado')->placeholder('—'),
                                TextEntry::make('status')->label('Estado')->badge(),
                            ])
                            ->placeholder('Ninguna ficha existente coincide.'),
                    ]),
            ]);
    }
}
