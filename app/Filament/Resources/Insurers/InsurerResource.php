<?php

namespace App\Filament\Resources\Insurers;

use App\Filament\Resources\Insurers\Pages\EditInsurer;
use App\Filament\Resources\Insurers\Pages\ListInsurers;
use App\Filament\Resources\Insurers\RelationManagers\DoctorsRelationManager;
use App\Filament\Resources\Insurers\RelationManagers\FacilitiesRelationManager;
use App\Filament\Resources\Insurers\Schemas\InsurerForm;
use App\Filament\Resources\Insurers\Tables\InsurersTable;
use App\Models\Insurer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Aseguradoras por país, sin planes (MODULO-ESTABLECIMIENTOS-SEGUROS.md §7.2).
 */
class InsurerResource extends Resource
{
    protected static ?string $model = Insurer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Directorio';

    protected static ?int $navigationSort = 35;

    protected static ?string $modelLabel = 'aseguradora';

    protected static ?string $pluralModelLabel = 'aseguradoras';

    protected static bool $isGloballySearchable = false;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return InsurerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InsurersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DoctorsRelationManager::class,
            FacilitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInsurers::route('/'),
            'edit' => EditInsurer::route('/{record}/edit'),
        ];
    }
}
