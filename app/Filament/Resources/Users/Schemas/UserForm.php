<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\Users\Actions\UserActions;
use App\Models\User;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * Crear = invitar (InviteUserAction). Editar = solo nombre y correo:
 * el estado y los roles cambian únicamente con sus acciones y su auditoría.
 */
class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(150),
                        TextInput::make('email')
                            ->label('Correo')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            // users_email_uniq es sobre lower(email).
                            ->rule(fn (?Model $record) => function (string $attribute, mixed $value, Closure $fail) use ($record) {
                                $taken = User::whereRaw('lower(email) = ?', [mb_strtolower(trim((string) $value))])
                                    ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                                    ->exists();

                                if ($taken) {
                                    $fail('Ya existe una cuenta con ese correo.');
                                }
                            }),
                        CheckboxList::make('roles')
                            ->label('Roles')
                            ->options(fn () => UserActions::roleOptions())
                            ->required()
                            ->columnSpanFull()
                            // Solo al invitar; después, con la acción "Asignar roles" (auditada).
                            ->visible(fn (string $operation) => $operation === 'create'
                                && (auth()->user()?->can('roles.manage') ?? false)),
                    ]),
            ]);
    }
}
