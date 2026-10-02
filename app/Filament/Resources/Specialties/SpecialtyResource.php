<?php

namespace App\Filament\Resources\Specialties;

use App\Filament\Resources\Specialties\Pages\EditSpecialty;
use App\Filament\Resources\Specialties\Pages\ListSpecialties;
use App\Filament\Resources\Specialties\RelationManagers\AliasesRelationManager;
use App\Filament\Resources\Specialties\Schemas\SpecialtyForm;
use App\Filament\Resources\Specialties\Tables\SpecialtiesTable;
use App\Models\Specialty;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SpecialtyResource extends Resource
{
    protected static ?string $model = Specialty::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static string|UnitEnum|null $navigationGroup = 'Directorio';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'especialidad';

    protected static ?string $pluralModelLabel = 'especialidades';

    protected static bool $isGloballySearchable = false;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return SpecialtyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SpecialtiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AliasesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpecialties::route('/'),
            'edit' => EditSpecialty::route('/{record}/edit'),
        ];
    }
}
