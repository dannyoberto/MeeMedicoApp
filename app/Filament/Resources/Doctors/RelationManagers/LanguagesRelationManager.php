<?php

namespace App\Filament\Resources\Doctors\RelationManagers;

use App\Domain\Directory\Actions\DoctorLanguagesAction;
use App\Domain\Directory\Enums\LanguageStatus;
use App\Filament\Resources\Doctors\RelationManagers\Concerns\ChangesDoctorAggregate;
use App\Models\Language;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LanguagesRelationManager extends RelationManager
{
    use ChangesDoctorAggregate;

    protected static string $relationship = 'languages';

    protected static ?string $title = 'Idiomas';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label('Idioma'),
                TextColumn::make('code')->label('Código')->color('gray'),
            ])
            ->headerActions([
                Action::make('attachLanguage')
                    ->label('Añadir idioma')
                    ->icon(Heroicon::OutlinedPlus)
                    ->schema([
                        Select::make('language_id')
                            ->label('Idioma')
                            ->options(fn () => Language::where('status', LanguageStatus::Active)
                                ->whereNotIn('id', $this->doctor()->languages()->pluck('languages.id'))
                                ->orderBy('name')->pluck('name', 'id'))
                            ->required(),
                    ])
                    ->authorize(fn () => $this->canChangeAggregate())
                    ->action(fn (array $data, Action $action) => $this->changeAggregate($action,
                        fn () => app(DoctorLanguagesAction::class)->attach($this->doctor(), Language::findOrFail($data['language_id']), auth()->user()),
                        'Idioma añadido')),
            ])
            ->recordActions([
                Action::make('detachLanguage')
                    ->label('Quitar')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn () => $this->canChangeAggregate())
                    ->action(fn (Language $record, Action $action) => $this->changeAggregate($action,
                        fn () => app(DoctorLanguagesAction::class)->detach($this->doctor(), $record, auth()->user()),
                        'Idioma quitado')),
            ])
            ->emptyStateHeading('Sin idiomas registrados');
    }
}
