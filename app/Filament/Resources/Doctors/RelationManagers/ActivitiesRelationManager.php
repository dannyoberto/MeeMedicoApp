<?php

namespace App\Filament\Resources\Doctors\RelationManagers;

use App\Filament\Resources\Activities\ActivityResource;
use App\Filament\Support\ActivityPresenter;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * Historial de la ficha: quién cambió qué y cuándo, sin salir de ella. Las Actions
 * registran cada cambio del agregado con el médico como sujeto (DATABASE.md §14.4).
 */
class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Historial';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('activity.view') ?? false;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function infolist(Schema $schema): Schema
    {
        return ActivityResource::infolist($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('causer'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime()->sortable(),
                TextColumn::make('event')->label('Evento')->badge()->color('gray')
                    ->formatStateUsing(fn (?string $state) => ActivityPresenter::eventLabel($state)),
                TextColumn::make('summary')->label('Resumen')
                    ->state(fn (Activity $record) => ActivityPresenter::summary($record))
                    ->limit(80)
                    ->placeholder('—'),
                TextColumn::make('causer.name')->label('Autor')->placeholder('Sistema / consola'),
            ])
            ->recordActions([
                ViewAction::make()->slideOver(),
            ])
            ->emptyStateHeading('Sin historial');
    }
}
