<?php

namespace App\Filament\Resources\Doctors\Tables;

use App\Domain\Directory\Enums\ClaimStatus;
use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Enums\VerificationStatus;
use App\Filament\Resources\Doctors\DoctorResource;
use App\Filament\Support\InitialsAvatarProvider;
use App\Models\Doctor;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DoctorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->where('status', '<>', DoctorStatus::Merged)
                ->with(['country:id,name', 'specialties' => fn ($q) => $q->wherePivot('is_primary', true)]))
            ->columns([
                ImageColumn::make('avatar')
                    ->label('')
                    ->state(fn (Doctor $record) => InitialsAvatarProvider::dataUri(DoctorResource::displayName($record)))
                    ->circular()
                    ->imageSize(36),
                TextColumn::make('last_name')
                    ->label('Médico')
                    ->formatStateUsing(fn (Doctor $record) => DoctorResource::displayName($record))
                    ->description(fn (Doctor $record) => $record->license_number ? "Lic. {$record->license_number}" : 'Sin licencia registrada')
                    ->searchable(['first_name', 'last_name', 'professional_name', 'license_number'])
                    ->sortable(['last_name', 'first_name']),
                TextColumn::make('specialties.name')
                    ->label('Especialidad')
                    ->placeholder('—'),
                TextColumn::make('country.name')
                    ->label('País'),
                TextColumn::make('status')
                    ->label('Publicación')
                    ->badge(),
                TextColumn::make('verification_status')
                    ->label('Verificación')
                    ->badge(),
                TextColumn::make('claim_status')
                    ->label('Reclamación')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('country_id')->label('País')->relationship('country', 'name'),
                SelectFilter::make('status')->label('Publicación')->options(
                    collect(DoctorStatus::cases())->reject(fn ($s) => $s === DoctorStatus::Merged)
                        ->mapWithKeys(fn ($s) => [$s->value => $s->getLabel()])->all(),
                ),
                SelectFilter::make('verification_status')->label('Verificación')->options(VerificationStatus::class),
                SelectFilter::make('claim_status')->label('Reclamación')->options(ClaimStatus::class),
                SelectFilter::make('specialty')
                    ->label('Especialidad')
                    ->relationship('specialties', 'name')
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->emptyStateHeading('Aún no hay médicos')
            ->emptyStateDescription('Crea una ficha o impórtalas en lote (Operación → Importación, próximamente).');
    }
}
