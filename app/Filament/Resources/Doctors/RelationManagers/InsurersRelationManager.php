<?php

namespace App\Filament\Resources\Doctors\RelationManagers;

use App\Domain\Directory\Actions\DoctorInsurersAction;
use App\Domain\Directory\Enums\InsurerStatus;
use App\Filament\Resources\Doctors\RelationManagers\Concerns\ChangesDoctorAggregate;
use App\Filament\Resources\Insurers\InsurerResource;
use App\Models\Insurer;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Aseguradoras por las que atiende el médico: solo de su país (DoctorInsurersAction).
 */
class InsurersRelationManager extends RelationManager
{
    use ChangesDoctorAggregate;

    protected static string $relationship = 'insurers';

    protected static ?string $title = 'Seguros';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Aseguradora'),
                TextColumn::make('type')->label('Tipo')->badge()->color('gray'),
                TextColumn::make('status')->label('Estado')->badge(),
            ])
            ->recordUrl(fn (Insurer $record) => InsurerResource::getUrl('edit', ['record' => $record]))
            ->headerActions([
                Action::make('attachInsurer')
                    ->label('Añadir aseguradora')
                    ->icon(Heroicon::OutlinedPlus)
                    ->schema([
                        Select::make('insurer_id')
                            ->label('Aseguradora')
                            ->helperText('Solo aseguradoras activas del país del médico.')
                            ->options(fn () => Insurer::where('country_id', $this->doctor()->country_id)
                                ->where('status', InsurerStatus::Active)
                                ->whereNotIn('id', $this->doctor()->insurers()->pluck('insurers.id'))
                                ->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->authorize(fn () => $this->canChangeAggregate())
                    ->action(fn (array $data, Action $action) => $this->changeAggregate($action,
                        fn () => app(DoctorInsurersAction::class)->attach($this->doctor(), Insurer::findOrFail($data['insurer_id']), auth()->user()),
                        'Aseguradora añadida')),
            ])
            ->recordActions([
                Action::make('detachInsurer')
                    ->label('Quitar')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn () => $this->canChangeAggregate())
                    ->action(fn (Insurer $record, Action $action) => $this->changeAggregate($action,
                        fn () => app(DoctorInsurersAction::class)->detach($this->doctor(), $record, auth()->user()),
                        'Aseguradora quitada')),
            ])
            ->emptyStateHeading('Sin aseguradoras registradas');
    }
}
