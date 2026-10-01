<?php

namespace App\Filament\Resources\ImportBatches\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ImportBatchInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Lote')
                    ->columns(4)
                    ->collapsible()
                    ->schema([
                        TextEntry::make('file_name')->label('Archivo'),
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
