<?php

namespace App\Filament\Resources\ImportBatches\Tables;

use App\Domain\Import\Enums\ImportBatchStatus;
use App\Models\ImportBatch;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ImportBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['country:id,name', 'creator:id,name']))
            ->columns([
                TextColumn::make('created_at')->label('Subido')->dateTime()->sortable(),
                TextColumn::make('file_name')->label('Archivo')->description(fn (ImportBatch $r) => $r->source)->searchable(),
                TextColumn::make('country.name')->label('País'),
                TextColumn::make('status')->label('Estado')->badge(),
                TextColumn::make('rows_total')->label('Filas')->numeric(),
                TextColumn::make('rows_review')->label('En revisión')->numeric()
                    ->color(fn (int $state) => $state > 0 ? 'warning' : 'gray'),
                TextColumn::make('rows_applied')->label('Aplicadas')->numeric(),
                TextColumn::make('rows_failed')->label('Fallidas')->numeric()
                    ->color(fn (int $state) => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('creator.name')->label('Por')->placeholder('Consola')->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('10s')
            ->filters([
                SelectFilter::make('country_id')->label('País')->relationship('country', 'name'),
                SelectFilter::make('status')->label('Estado')->options(ImportBatchStatus::class),
            ])
            ->recordActions([ViewAction::make()->label('Abrir')])
            ->emptyStateHeading('Aún no hay lotes')
            ->emptyStateDescription('Descarga la plantilla, llénala y súbela con "Subir lote".');
    }
}
