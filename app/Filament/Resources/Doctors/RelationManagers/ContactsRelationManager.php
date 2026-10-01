<?php

namespace App\Filament\Resources\Doctors\RelationManagers;

use App\Domain\Directory\Actions\DoctorContactsAction;
use App\Domain\Directory\Enums\ContactType;
use App\Filament\Resources\Doctors\RelationManagers\Concerns\ChangesDoctorAggregate;
use App\Models\DoctorContact;
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
use Illuminate\Database\Eloquent\Model;

/**
 * Contactos del médico. En Fase 1 el paciente ve la ficha y llama: un contacto
 * público es requisito de publicación. Autorizado por contacts.view / contacts.update.
 */
class ContactsRelationManager extends RelationManager
{
    use ChangesDoctorAggregate;

    protected static string $relationship = 'contacts';

    protected static ?string $title = 'Contactos';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('contacts.view') ?? false;
    }

    protected function canChangeAggregate(): bool
    {
        return auth()->user()?->can('manageContacts', $this->doctor()) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('value')
            ->modifyQueryUsing(fn ($query) => $query->with('location:id,address'))
            ->defaultSort('type')
            ->columns([
                TextColumn::make('type')->label('Tipo')->badge()->color('gray'),
                TextColumn::make('value')->label('Valor')
                    ->description(fn (DoctorContact $record) => $record->label)
                    ->copyable(),
                TextColumn::make('value_normalized')->label('Normalizado')->color('gray')->toggleable(),
                TextColumn::make('location.address')->label('Ubicación')->placeholder('General'),
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
                        fn () => app(DoctorContactsAction::class)->save($this->doctor(), null, $data, auth()->user()),
                        'Contacto añadido')),
            ])
            ->recordActions([
                Action::make('editContact')
                    ->label('Editar')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('gray')
                    ->fillForm(fn (DoctorContact $record) => [
                        'type' => $record->type->value,
                        ...$record->only(['value', 'label', 'location_id', 'is_public', 'is_primary']),
                    ])
                    ->schema($this->contactFields())
                    ->authorize(fn () => $this->canChangeAggregate())
                    ->action(fn (array $data, DoctorContact $record, Action $action) => $this->changeAggregate($action,
                        fn () => app(DoctorContactsAction::class)->save($this->doctor(), $record, $data, auth()->user()),
                        'Contacto actualizado')),
                Action::make('removeContact')
                    ->label('Quitar')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn () => $this->canChangeAggregate())
                    ->action(fn (DoctorContact $record, Action $action) => $this->changeAggregate($action,
                        fn () => app(DoctorContactsAction::class)->remove($this->doctor(), $record, auth()->user()),
                        'Contacto quitado')),
            ])
            ->emptyStateHeading('Sin contactos')
            ->emptyStateDescription('Hace falta al menos un contacto público para publicar.');
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
                        ? 'Como lo daría el médico; se guarda en formato internacional (E.164).'
                        : null),
            ]),
            TextInput::make('label')->label('Etiqueta')->placeholder('Consultorio, Emergencias…')->maxLength(100),
            Select::make('location_id')
                ->label('Ubicación')
                ->helperText('Déjalo vacío si es el contacto general del médico.')
                ->options(fn () => $this->doctor()->locations()->pluck('address', 'locations.id')),
            Grid::make(2)->schema([
                Toggle::make('is_public')->label('Visible en la ficha pública')->default(true),
                Toggle::make('is_primary')->label('Principal de su tipo'),
            ]),
        ];
    }
}
