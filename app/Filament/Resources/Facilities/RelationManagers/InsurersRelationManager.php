<?php

namespace App\Filament\Resources\Facilities\RelationManagers;

use App\Domain\Directory\Actions\FacilityInsurersAction;
use App\Domain\Directory\Enums\InsurerStatus;
use App\Filament\Resources\Facilities\RelationManagers\Concerns\ChangesFacilityAggregate;
use App\Filament\Resources\Insurers\InsurerResource;
use App\Models\Insurer;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Convenios del establecimiento con aseguradoras de su país (FacilityInsurersAction).
 * Independientes de las aseguradoras de cada uno de sus médicos.
 */
class InsurersRelationManager extends RelationManager
{
    use ChangesFacilityAggregate;

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
                    ->label('Añadir convenio')
                    ->icon(Heroicon::OutlinedPlus)
                    ->modalDescription('Que el establecimiento tenga convenio no significa que todos sus médicos atiendan por esa aseguradora: eso se registra en cada médico.')
                    ->schema([
                        Select::make('insurer_id')
                            ->label('Aseguradora')
                            ->options(fn () => Insurer::where('country_id', $this->facility()->country_id)
                                ->where('status', InsurerStatus::Active)
                                ->whereNotIn('id', $this->facility()->insurers()->pluck('insurers.id'))
                                ->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->authorize(fn () => $this->canChangeAggregate())
                    ->action(fn (array $data, Action $action) => $this->changeAggregate($action,
                        fn () => app(FacilityInsurersAction::class)->attach($this->facility(), Insurer::findOrFail($data['insurer_id']), auth()->user()),
                        'Convenio añadido')),
            ])
            ->recordActions([
                Action::make('detachInsurer')
                    ->label('Quitar')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn () => $this->canChangeAggregate())
                    ->action(fn (Insurer $record, Action $action) => $this->changeAggregate($action,
                        fn () => app(FacilityInsurersAction::class)->detach($this->facility(), $record, auth()->user()),
                        'Convenio quitado')),
            ])
            ->emptyStateHeading('Sin convenios registrados');
    }
}
