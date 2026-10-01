<?php

namespace App\Filament\Resources\Doctors\RelationManagers;

use App\Domain\Directory\Actions\DoctorSpecialtiesAction;
use App\Domain\Directory\Enums\SpecialtyStatus;
use App\Filament\Resources\Doctors\RelationManagers\Concerns\ChangesDoctorAggregate;
use App\Models\Specialty;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Especialidades del médico. La principal encabeza la ficha; se gestionan con
 * DoctorSpecialtiesAction (no con Attach/Detach genéricos de Filament).
 */
class SpecialtiesRelationManager extends RelationManager
{
    use ChangesDoctorAggregate;

    protected static string $relationship = 'specialties';

    protected static ?string $title = 'Especialidades';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('doctor_specialties.is_primary', 'desc')
            ->columns([
                TextColumn::make('name')->label('Especialidad'),
                IconColumn::make('pivot.is_primary')->label('Principal')->boolean()
                    ->trueIcon(Heroicon::OutlinedStar)->falseIcon(Heroicon::OutlinedMinus)
                    ->trueColor('primary')->falseColor('gray'),
                TextColumn::make('status')->label('Estado')->badge(),
            ])
            ->headerActions([
                Action::make('attachSpecialty')
                    ->label('Añadir especialidad')
                    ->icon(Heroicon::OutlinedPlus)
                    ->schema([
                        Select::make('specialty_id')
                            ->label('Especialidad')
                            ->options(fn () => Specialty::where('status', SpecialtyStatus::Active)
                                ->whereNotIn('id', $this->doctor()->specialties()->pluck('specialties.id'))
                                ->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Toggle::make('primary')->label('Marcar como principal')
                            ->helperText('La primera que se añade es principal automáticamente.'),
                    ])
                    ->authorize(fn () => $this->canChangeAggregate())
                    ->action(fn (array $data, Action $action) => $this->changeAggregate($action,
                        fn () => app(DoctorSpecialtiesAction::class)->attach($this->doctor(), Specialty::findOrFail($data['specialty_id']), auth()->user(), (bool) ($data['primary'] ?? false)),
                        'Especialidad añadida')),
            ])
            ->recordActions([
                Action::make('makePrimary')
                    ->label('Hacer principal')
                    ->icon(Heroicon::OutlinedStar)
                    ->color('gray')
                    ->visible(fn (Specialty $record) => ! $record->pivot->is_primary)
                    ->authorize(fn () => $this->canChangeAggregate())
                    ->action(fn (Specialty $record, Action $action) => $this->changeAggregate($action,
                        fn () => app(DoctorSpecialtiesAction::class)->makePrimary($this->doctor(), $record, auth()->user()),
                        'Especialidad principal actualizada')),
                Action::make('detachSpecialty')
                    ->label('Quitar')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn () => $this->canChangeAggregate())
                    ->action(fn (Specialty $record, Action $action) => $this->changeAggregate($action,
                        fn () => app(DoctorSpecialtiesAction::class)->detach($this->doctor(), $record, auth()->user()),
                        'Especialidad quitada')),
            ])
            ->emptyStateHeading('Sin especialidades')
            ->emptyStateDescription('Hace falta al menos una para publicar.');
    }
}
