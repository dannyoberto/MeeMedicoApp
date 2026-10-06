<?php

namespace App\Filament\Resources\Facilities\RelationManagers;

use App\Domain\Directory\Actions\FacilityContactsAction;
use App\Domain\Directory\Enums\ContactType;
use App\Filament\Resources\Facilities\RelationManagers\Concerns\ChangesFacilityAggregate;
use App\Models\FacilityContact;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Contactos del establecimiento: central, emergencias, citas (DATABASE.md §9.12).
 * Un contacto público es requisito para activarlo.
 */
class ContactsRelationManager extends RelationManager
{
    use ChangesFacilityAggregate;

    protected static string $relationship = 'contacts';

    protected static ?string $title = 'Contactos';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('value')
            ->modifyQueryUsing(fn ($query) => $query->with('location:id,address'))
            ->defaultSort('type')
            ->columns([
                TextColumn::make('type')->label('Tipo')->badge()->color('gray'),
                TextColumn::make('value')->label('Valor')
                    ->description(fn (FacilityContact $record) => $record->label)
                    ->copyable(),
                TextColumn::make('value_normalized')->label('Normalizado')->color('gray')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('location.address')->label('Sede')->placeholder('General'),
                IconColumn::make('is_public')->label('Público')->boolean(),
                IconColumn::make('is_primary')->label('Principal')->boolean()
                    ->trueIcon(Heroicon::OutlinedStar)->falseIcon(Heroicon::OutlinedMinus)
                    ->trueColor('primary')->falseColor('gray'),
            ])
            ->headerActions([
                Action::make('addContact')
                    ->label('Añadir contacto')
                    ->icon(Heroicon::OutlinedPlus)
                    ->schema($this->contactFields())
                    ->authorize(fn () => $this->canChangeAggregate())
                    ->action(fn (array $data, Action $action) => $this->changeAggregate($action,
                        fn () => app(FacilityContactsAction::class)->save($this->facility(), null, $data, auth()->user()),
                        'Contacto añadido')),
            ])
            ->recordActions([
                Action::make('editContact')
                    ->label('Editar')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('gray')
                    ->fillForm(fn (FacilityContact $record) => [
                        'type' => $record->type->value,
                        ...$record->only(['value', 'label', 'location_id', 'is_public', 'is_primary']),
                    ])
                    ->schema($this->contactFields())
                    ->authorize(fn () => $this->canChangeAggregate())
                    ->action(fn (array $data, FacilityContact $record, Action $action) => $this->changeAggregate($action,
                        fn () => app(FacilityContactsAction::class)->save($this->facility(), $record, $data, auth()->user()),
                        'Contacto actualizado')),
                Action::make('removeContact')
                    ->label('Quitar')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn () => $this->canChangeAggregate())
                    ->action(fn (FacilityContact $record, Action $action) => $this->changeAggregate($action,
                        fn () => app(FacilityContactsAction::class)->remove($this->facility(), $record, auth()->user()),
                        'Contacto quitado')),
            ])
            ->emptyStateHeading('Sin contactos')
            ->emptyStateDescription('Hace falta al menos un contacto público para activarlo.');
    }

    /**
     * @return array<int, mixed>
     */
    private function contactFields(): array
    {
        return [
            Grid::make(2)->schema([
                Select::make('type')->label('Tipo')->options(ContactType::class)->required()->live(),
                TextInput::make('value')
                    ->label('Valor')
                    ->required()
                    ->maxLength(255)
                    ->helperText(fn (Get $get) => in_array($get('type'), ['phone', 'mobile', 'whatsapp'], true)
                        ? 'Como lo publica el establecimiento; se guarda en formato internacional (E.164).'
                        : null),
            ]),
            TextInput::make('label')->label('Etiqueta')->placeholder('Central, Emergencias, Citas…')->maxLength(100),
            Select::make('location_id')
                ->label('Sede')
                ->helperText('Déjalo vacío si es un contacto general del establecimiento.')
                ->options(fn () => $this->facility()->locations()->pluck('address', 'id')),
            Grid::make(2)->schema([
                Toggle::make('is_public')->label('Visible en la página pública')->default(true),
                Toggle::make('is_primary')->label('Principal de su tipo'),
            ]),
        ];
    }
}
