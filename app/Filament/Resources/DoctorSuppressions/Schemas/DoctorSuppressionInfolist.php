<?php

namespace App\Filament\Resources\DoctorSuppressions\Schemas;

use App\Domain\Directory\Support\SuppressionMatches;
use App\Filament\Resources\Doctors\DoctorResource;
use App\Models\DoctorSuppression;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

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
                        TextEntry::make('state')
                            ->label('Estado')
                            ->state(fn (DoctorSuppression $record) => $record->isRevoked() ? 'Revocada' : 'Vigente')
                            ->badge()
                            ->color(fn (string $state) => $state === 'Vigente' ? 'danger' : 'gray'),
                        TextEntry::make('reason')->label('Motivo')->placeholder('—')->columnSpan(2),
                    ]),
                Section::make('Revocación')
                    ->description('La persona pidió volver a aparecer. La supresión ya no bloquea.')
                    ->visible(fn (DoctorSuppression $record) => $record->isRevoked())
                    ->columns(3)
                    ->schema([
                        TextEntry::make('revocation_requested_at')->label('Solicitada')->dateTime(),
                        TextEntry::make('revoked_at')->label('Registrada')->dateTime(),
                        TextEntry::make('revoker.name')->label('Registrada por')->placeholder('Sistema / consola'),
                        TextEntry::make('revocation_reason')->label('Canal y verificación')->columnSpanFull(),
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
                    ->description(fn (DoctorSuppression $record) => $record->isRevoked()
                        ? 'Revocada: estas fichas pueden publicarse de nuevo desde su ficha.'
                        : 'Mientras esté vigente, ninguna de estas fichas puede publicarse por colegiado, teléfono o correo. Las ya publicadas se retiran con «Despublicar coincidencias».')
                    ->schema([
                        RepeatableEntry::make('matches')
                            ->hiddenLabel()
                            ->state(fn (DoctorSuppression $record) => SuppressionMatches::for($record)
                                ->map(fn ($doctor) => [
                                    'name' => new HtmlString(sprintf(
                                        '<a href="%s" class="underline">%s</a>',
                                        e(DoctorResource::getUrl('edit', ['record' => $doctor])),
                                        e(trim("{$doctor->first_name} {$doctor->last_name}")),
                                    )),
                                    'license' => $doctor->license_number,
                                    'status' => $doctor->status,
                                ])->all())
                            ->columns(3)
                            ->schema([
                                TextEntry::make('name')->label('Médico')->html(),
                                TextEntry::make('license')->label('Colegiado')->placeholder('—'),
                                TextEntry::make('status')->label('Estado')->badge(),
                            ])
                            ->placeholder('Ninguna ficha existente coincide.'),
                    ]),
            ]);
    }
}
