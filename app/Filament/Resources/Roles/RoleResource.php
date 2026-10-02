<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\ManageRoles;
use App\Models\Role;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Solo lectura. Qué permisos tiene cada rol se define en PermissionSeeder (fuente
 * de verdad, resincronizada en cada `db:seed`); a los usuarios se les asignan roles
 * desde Usuarios → "Asignar roles".
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Plataforma';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'rol';

    protected static ?string $pluralModelLabel = 'roles';

    protected static bool $isGloballySearchable = false;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Áreas de permisos, por prefijo (DATABASE.md §16.2).
     */
    private const AREAS = [
        'backoffice' => 'Backoffice',
        'doctors' => 'Médicos',
        'profiles' => 'Perfiles (cualquiera)',
        'profile' => 'Perfil propio',
        'specialties' => 'Especialidades',
        'locations' => 'Ubicaciones',
        'geography' => 'Geografía',
        'contacts' => 'Contactos',
        'claims' => 'Reclamaciones',
        'imports' => 'Importación',
        'suppressions' => 'Supresiones',
        'users' => 'Usuarios',
        'roles' => 'Roles',
        'activity' => 'Auditoría',
    ];

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Permisos')
                    ->description('Se definen en database/seeders/PermissionSeeder.php y se revisan como código. Los cambios hechos aquí se perderían en el siguiente db:seed, por eso esta vista es de solo lectura.')
                    ->columns(2)
                    ->schema(collect(self::AREAS)->map(
                        fn (string $label, string $prefix) => TextEntry::make("area_{$prefix}")
                            ->label($label)
                            ->state(fn (Role $record) => $record->permissions
                                ->pluck('name')
                                ->filter(fn (string $name) => Str::before($name, '.') === $prefix)
                                ->map(fn (string $name) => Str::after($name, '.'))
                                ->values()
                                ->all())
                            ->badge()
                            ->color('primary')
                            ->visible(fn (Role $record) => $record->permissions->contains(
                                fn ($permission) => Str::before($permission->name, '.') === $prefix,
                            )),
                    )->values()->all()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Rol')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('users_count')
                    ->label('Usuarios')
                    ->counts('users')
                    ->numeric(),
                TextColumn::make('permissions_count')
                    ->label('Permisos')
                    ->counts('permissions')
                    ->numeric(),
                TextColumn::make('guard_name')
                    ->label('Guard')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRoles::route('/'),
        ];
    }
}
