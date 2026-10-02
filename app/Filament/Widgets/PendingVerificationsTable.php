<?php

namespace App\Filament\Widgets;

use App\Domain\Directory\Enums\VerificationStatus;
use App\Domain\Directory\Support\DoctorSearch;
use App\Filament\Resources\Doctors\DoctorResource;
use App\Filament\Support\InitialsAvatarProvider;
use App\Models\Doctor;
use Filament\Actions\Action;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cola de verificación. "Revisar" abre la ficha, donde vive la acción Verificar.
 */
class PendingVerificationsTable extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('doctors.verify') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Verificaciones pendientes')
            ->query(Doctor::query()
                ->where('verification_status', VerificationStatus::Pending)
                ->with(['country:id,name', 'specialties' => fn ($q) => $q->wherePivot('is_primary', true)]))
            ->defaultSort('updated_at')
            ->splitSearchTerms(false)
            ->paginated([5])
            ->headerActions([
                Action::make('viewAll')
                    ->label('Ver todas')
                    ->link()
                    ->url(DoctorResource::getUrl('index', ['tab' => 'verification'])),
            ])
            ->columns([
                ImageColumn::make('avatar')
                    ->label('')
                    ->state(fn (Doctor $record) => InitialsAvatarProvider::dataUri("{$record->first_name} {$record->last_name}"))
                    ->circular()
                    ->imageSize(36),
                TextColumn::make('last_name')
                    ->label('Médico')
                    ->formatStateUsing(fn (Doctor $record) => $record->professional_name ?: "{$record->first_name} {$record->last_name}")
                    ->description(fn (Doctor $record) => $record->license_number ? "Lic. {$record->license_number}" : 'Sin licencia registrada')
                    ->searchable(query: fn (Builder $query, string $search) => DoctorSearch::apply($query, $search)),
                TextColumn::make('specialties.name')
                    ->label('Especialidad')
                    ->placeholder('—'),
                TextColumn::make('country.name')
                    ->label('País'),
                TextColumn::make('verification_status')
                    ->label('Estado')
                    ->badge(),
                TextColumn::make('updated_at')
                    ->label('Solicitado')
                    ->since()
                    ->sortable(),
            ])
            ->recordUrl(fn (Doctor $record) => DoctorResource::getUrl('edit', ['record' => $record]))
            ->recordActions([
                Action::make('review')
                    ->label('Revisar')
                    ->button()
                    ->color('gray')
                    ->url(fn (Doctor $record) => DoctorResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading('No hay verificaciones pendientes')
            ->emptyStateDescription('Cuando un médico aporte documentación o un claim lo requiera, aparecerá aquí.')
            ->emptyStateIcon('heroicon-o-shield-check');
    }
}
