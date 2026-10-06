<?php

namespace App\Filament\Resources\Doctors;

use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Enums\VerificationStatus;
use App\Domain\Directory\Support\DoctorSearch;
use App\Filament\Resources\Doctors\Pages\CreateDoctor;
use App\Filament\Resources\Doctors\Pages\EditDoctor;
use App\Filament\Resources\Doctors\Pages\ListDoctors;
use App\Filament\Resources\Doctors\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\Doctors\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\Doctors\RelationManagers\ExternalReferencesRelationManager;
use App\Filament\Resources\Doctors\RelationManagers\InsurersRelationManager;
use App\Filament\Resources\Doctors\RelationManagers\LanguagesRelationManager;
use App\Filament\Resources\Doctors\RelationManagers\LocationsRelationManager;
use App\Filament\Resources\Doctors\RelationManagers\SpecialtiesRelationManager;
use App\Filament\Resources\Doctors\Schemas\DoctorForm;
use App\Filament\Resources\Doctors\Tables\DoctorsTable;
use App\Models\Doctor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class DoctorResource extends Resource
{
    protected static ?string $model = Doctor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Directorio';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'médico';

    protected static ?string $pluralModelLabel = 'médicos';

    protected static ?string $recordTitleAttribute = 'last_name';

    public static function getRecordTitle(?Model $record): string
    {
        return $record ? self::displayName($record) : 'Médico';
    }

    public static function displayName(Doctor $doctor): string
    {
        return $doctor->professional_name ?: trim("{$doctor->first_name} {$doctor->last_name}");
    }

    /**
     * Cola de verificación en el menú (índice parcial doctors_verification_pending_idx).
     */
    public static function getNavigationBadge(): ?string
    {
        if (! (auth()->user()?->can('doctors.verify') ?? false)) {
            return null;
        }

        $pending = Doctor::where('verification_status', VerificationStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Verificaciones pendientes';
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'last_name', 'professional_name', 'license_number'];
    }

    /**
     * Sin acentos, en cualquier orden y por colegiado: la misma búsqueda que la tabla.
     */
    protected static function applyGlobalSearchAttributeConstraints(Builder $query, string $search): void
    {
        DoctorSearch::apply($query, $search);
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->where('status', '<>', DoctorStatus::Merged)
            ->with('country:id,name');
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter([
            'Licencia' => $record->license_number,
            'País' => $record->country?->name,
            'Estado' => $record->status?->getLabel(),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return DoctorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DoctorsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SpecialtiesRelationManager::class,
            LocationsRelationManager::class,
            ContactsRelationManager::class,
            LanguagesRelationManager::class,
            InsurersRelationManager::class,
            ExternalReferencesRelationManager::class,
            ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDoctors::route('/'),
            'create' => CreateDoctor::route('/create'),
            'edit' => EditDoctor::route('/{record}/edit'),
        ];
    }
}
