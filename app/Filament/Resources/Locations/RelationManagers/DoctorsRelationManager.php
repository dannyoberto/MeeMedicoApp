<?php

namespace App\Filament\Resources\Locations\RelationManagers;

use App\Domain\Directory\Enums\LocationType;
use App\Filament\Resources\Doctors\DoctorResource;
use App\Models\Doctor;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Quién atiende en esta ubicación (MODELO-DOMINIO.md §2.4). Solo lectura: se asocia y
 * se quita desde la ficha de cada médico. Sirve para saber a quién afecta editarla.
 */
class DoctorsRelationManager extends RelationManager
{
    protected static string $relationship = 'doctors';

    protected static ?string $title = 'Médicos en esta ubicación';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('last_name')
            ->modifyQueryUsing(fn ($query) => $query->with('country:id,name'))
            ->defaultSort('last_name')
            ->columns([
                TextColumn::make('last_name')->label('Médico')
                    ->formatStateUsing(fn (Doctor $record) => DoctorResource::displayName($record))
                    ->description(fn (Doctor $record) => $record->license_number ? "Lic. {$record->license_number}" : null),
                TextColumn::make('pivot.location_type')->label('Tipo')
                    ->formatStateUsing(fn (?string $state) => $state ? LocationType::from($state)->getLabel() : '—'),
                IconColumn::make('pivot.is_primary')->label('Principal')->boolean()
                    ->trueIcon(Heroicon::OutlinedStar)->falseIcon(Heroicon::OutlinedMinus)
                    ->trueColor('primary')->falseColor('gray'),
                TextColumn::make('status')->label('Publicación')->badge(),
            ])
            ->recordUrl(fn (Doctor $record) => DoctorResource::getUrl('edit', ['record' => $record]))
            ->emptyStateHeading('Ningún médico atiende aquí');
    }
}
