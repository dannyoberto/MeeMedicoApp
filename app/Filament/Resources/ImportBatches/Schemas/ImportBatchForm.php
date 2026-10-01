<?php

namespace App\Filament\Resources\ImportBatches\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Subir un lote. El archivo debe ser la plantilla del mismo país (se valida al leerlo).
 */
class ImportBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->description('Al subirlo se leen las filas, se normalizan y se buscan coincidencias automáticamente. Después revisas las dudosas y decides cuándo aplicar. Nada se publica hasta que pulses "Publicar".')
                    ->columns(2)
                    ->schema([
                        Select::make('country_id')
                            ->label('País')
                            ->relationship('country', 'name')
                            ->required(),
                        TextInput::make('source')
                            ->label('Fuente')
                            ->helperText('Usa siempre la misma para el mismo origen: junto al "ID del médico" permite reconocer fichas ya cargadas.')
                            ->default(config('import.default_source'))
                            ->required()
                            ->maxLength(50),
                        FileUpload::make('file')
                            ->label('Archivo Excel (plantilla de MeeMedico)')
                            ->disk('local')
                            ->directory('imports/uploads')
                            ->visibility('private')
                            ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                            ->maxSize((int) config('import.max_upload_kb'))
                            ->storeFileNamesIn('file_name')
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
