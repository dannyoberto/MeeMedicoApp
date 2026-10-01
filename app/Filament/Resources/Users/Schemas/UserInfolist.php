<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')->label('Nombre'),
                        TextEntry::make('email')->label('Correo')->copyable(),
                        TextEntry::make('status')->label('Estado')->badge(),
                        TextEntry::make('roles.name')->label('Roles')->badge()->color('primary')->placeholder('Sin roles'),
                        TextEntry::make('email_verified_at')
                            ->label('Correo verificado')
                            ->dateTime()
                            ->placeholder('Pendiente: aún no usó el enlace de invitación'),
                        TextEntry::make('last_login_at')->label('Último acceso')->dateTime()->placeholder('Nunca'),
                        TextEntry::make('created_at')->label('Alta')->dateTime(),
                    ]),
            ]);
    }
}
