<?php

namespace App\Filament\Resources\Doctors\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Identificadores del médico en fuentes externas (DATABASE.md §9.8). Solo lectura:
 * los escribe el importador y los traspasa la fusión. Dejar de verse en la fuente
 * (last_seen_at antiguo) es la señal para revisar una baja.
 */
class ExternalReferencesRelationManager extends RelationManager
{
    protected static string $relationship = 'externalReferences';

    protected static ?string $title = 'Referencias externas';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference')
            ->columns([
                TextColumn::make('source')->label('Fuente')->badge()->color('gray'),
                TextColumn::make('reference')->label('Identificador')->copyable(),
                TextColumn::make('first_seen_at')->label('Visto por primera vez')->date(),
                TextColumn::make('last_seen_at')->label('Última vez')->since(),
            ])
            ->emptyStateHeading('Sin referencias externas')
            ->emptyStateDescription('Se registran al importar desde un padrón o colegio médico.');
    }
}
