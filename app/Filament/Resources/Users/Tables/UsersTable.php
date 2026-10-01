<?php

namespace App\Filament\Resources\Users\Tables;

use App\Domain\Identity\Enums\UserStatus;
use App\Filament\Resources\Users\Actions\UserActions;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
                TextColumn::make('last_login_at')
                    ->label('Último acceso')
                    ->since()
                    ->placeholder('Nunca')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Alta')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(UserStatus::class),
                SelectFilter::make('roles')
                    ->label('Rol')
                    ->relationship('roles', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ActionGroup::make([
                    UserActions::assignRoles(),
                    UserActions::resendInvitation(),
                    UserActions::suspend(),
                    UserActions::reactivate(),
                ]),
            ]);
    }
}
