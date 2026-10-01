<?php

namespace App\Filament\Resources\DoctorSuppressions;

use App\Filament\Resources\DoctorSuppressions\Pages\CreateDoctorSuppression;
use App\Filament\Resources\DoctorSuppressions\Pages\ListDoctorSuppressions;
use App\Filament\Resources\DoctorSuppressions\Pages\ViewDoctorSuppression;
use App\Filament\Resources\DoctorSuppressions\Schemas\DoctorSuppressionForm;
use App\Filament\Resources\DoctorSuppressions\Schemas\DoctorSuppressionInfolist;
use App\Filament\Resources\DoctorSuppressions\Tables\DoctorSuppressionsTable;
use App\Models\DoctorSuppression;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Personas que pidieron no aparecer en el directorio (DATABASE.md §14.1).
 * Se crean, se consultan y se revocan si la persona quiere volver; nunca se editan ni se borran.
 */
class DoctorSuppressionResource extends Resource
{
    protected static ?string $model = DoctorSuppression::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static string|UnitEnum|null $navigationGroup = 'Directorio';

    protected static ?int $navigationSort = 90;

    protected static ?string $modelLabel = 'supresión';

    protected static ?string $pluralModelLabel = 'supresiones';

    public static function form(Schema $schema): Schema
    {
        return DoctorSuppressionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DoctorSuppressionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DoctorSuppressionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDoctorSuppressions::route('/'),
            'create' => CreateDoctorSuppression::route('/create'),
            'view' => ViewDoctorSuppression::route('/{record}'),
        ];
    }
}
