<?php

namespace App\Filament\Resources\ImportBatches\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ImportBatchInfolist
{
    /**
     * A todo el ancho: en la rejilla de 2 de la vista, la tarjeta quedaba a media página
     * y partía las fechas en dos líneas. El nombre del archivo ya es el título.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Lote')
                    ->columns(['sm' => 2, 'lg' => 5])
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('country.name')->label('País'),
                        TextEntry::make('source')->label('Fuente')->badge()->color('gray'),
                        TextEntry::make('creator.name')->label('Subido por')->placeholder('Consola'),
                        TextEntry::make('created_at')->label('Subido')->dateTime(),
                        TextEntry::make('finished_at')->label('Completado')->dateTime()->placeholder('—'),
                        TextEntry::make('notes')->label('Notas')->placeholder('—')->columnSpanFull()
                            ->formatStateUsing(fn (?string $state) => $state ? nl2br(e($state)) : null)->html(),
                    ]),
            ]);
    }
}
