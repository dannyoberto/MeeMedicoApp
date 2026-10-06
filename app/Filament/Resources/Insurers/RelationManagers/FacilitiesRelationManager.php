<?php

namespace App\Filament\Resources\Insurers\RelationManagers;

use App\Domain\Directory\Actions\FacilityInsurersAction;
use App\Filament\Resources\Facilities\FacilityResource;
use App\Filament\Support\DomainAction;
use App\Models\Facility;
use App\Models\Insurer;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Establecimientos con convenio con esta aseguradora (FacilityInsurersAction).
 */
class FacilitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'facilities';

    protected static ?string $title = 'Establecimientos';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('facilities.view') ?? false;
    }

    private function insurer(): Insurer
    {
        /** @var Insurer */
        return $this->getOwnerRecord();
    }

    private function canLink(): bool
    {
        return auth()->user()?->can('facilities.update') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Establecimiento')->searchable(),
                TextColumn::make('type')->label('Tipo')->badge()->color('gray'),
                TextColumn::make('status')->label('Estado')->badge(),
            ])
            ->recordUrl(fn (Facility $record) => FacilityResource::getUrl('edit', ['record' => $record]))
            ->headerActions([
                Action::make('attachFacility')
                    ->label('Añadir establecimiento')
                    ->icon(Heroicon::OutlinedPlus)
                    ->schema([
                        Select::make('facility_id')
                            ->label('Establecimiento')
                            ->options(fn () => Facility::where('country_id', $this->insurer()->country_id)
                                ->whereDoesntHave('insurers', fn ($q) => $q->whereKey($this->insurer()->getKey()))
                                ->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->authorize(fn () => $this->canLink())
                    ->action(function (array $data, Action $action) {
                        DomainAction::run($action, fn () => app(FacilityInsurersAction::class)->attach(Facility::findOrFail($data['facility_id']), $this->insurer(), auth()->user()));
                        Notification::make()->success()->title('Establecimiento añadido')->send();
                    }),
            ])
            ->recordActions([
                Action::make('detachFacility')
                    ->label('Quitar')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn () => $this->canLink())
                    ->action(function (Facility $record, Action $action) {
                        DomainAction::run($action, fn () => app(FacilityInsurersAction::class)->detach($record, $this->insurer(), auth()->user()));
                        Notification::make()->success()->title('Establecimiento quitado')->send();
                    }),
            ])
            ->emptyStateHeading('Ningún establecimiento con convenio registrado');
    }
}
