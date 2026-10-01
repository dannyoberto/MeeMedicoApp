<?php

namespace App\Filament\Resources\Activities;

use App\Filament\Resources\Activities\Pages\ManageActivities;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

/**
 * Visor de la auditoría (DATABASE.md §14.4, MODELO-IDENTIDAD.md §10). Solo lectura:
 * es el único registro que permite deshacer a mano una fusión equivocada.
 */
class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Plataforma';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'evento de auditoría';

    protected static ?string $pluralModelLabel = 'auditoría';

    protected static ?string $navigationLabel = 'Auditoría';

    /**
     * Nombre legible del tipo de sujeto.
     */
    private const SUBJECTS = [
        'App\Models\User' => 'Usuario',
        'App\Models\Doctor' => 'Médico',
        'App\Models\DoctorClaim' => 'Reclamación',
        'App\Models\DoctorSuppression' => 'Supresión',
        'App\Models\Specialty' => 'Especialidad',
        'App\Models\Region' => 'Región',
        'App\Models\City' => 'Ciudad',
        'App\Models\ImportBatch' => 'Lote de importación',
        'App\Models\ImportRow' => 'Fila de importación',
    ];

    public static function subjectLabel(?string $type): string
    {
        return $type ? (self::SUBJECTS[$type] ?? class_basename($type)) : '—';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('created_at')->label('Fecha')->dateTime(),
                        TextEntry::make('event')->label('Evento')->badge()->color('gray'),
                        TextEntry::make('causer.name')->label('Autor')->placeholder('Sistema / consola'),
                        TextEntry::make('subject_type')->label('Sujeto')->formatStateUsing(fn (?string $state) => self::subjectLabel($state)),
                        TextEntry::make('subject_id')->label('ID del sujeto')->copyable()->placeholder('—'),
                        TextEntry::make('description')->label('Descripción'),
                    ]),
                Section::make('Datos registrados')
                    ->schema([
                        KeyValueEntry::make('properties')
                            ->label('Propiedades')
                            ->keyLabel('Clave')
                            ->valueLabel('Valor')
                            ->state(fn (Activity $record) => self::flatten($record->properties?->all() ?? []))
                            ->placeholder('Sin propiedades'),
                        KeyValueEntry::make('attribute_changes')
                            ->label('Cambios de atributos')
                            ->keyLabel('Clave')
                            ->valueLabel('Valor')
                            ->state(fn (Activity $record) => self::flatten($record->attribute_changes?->all() ?? []))
                            ->placeholder('Sin cambios registrados'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('event')
                    ->label('Evento')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('subject_type')
                    ->label('Sujeto')
                    ->formatStateUsing(fn (?string $state) => self::subjectLabel($state)),
                TextColumn::make('subject_id')
                    ->label('ID del sujeto')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('causer.name')
                    ->label('Autor')
                    ->placeholder('Sistema / consola'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('event')
                    ->label('Evento')
                    ->options(fn () => Activity::query()->distinct()->orderBy('event')->pluck('event', 'event')->filter()->all()),
                SelectFilter::make('subject_type')
                    ->label('Tipo de sujeto')
                    ->options(fn () => Activity::query()->distinct()->whereNotNull('subject_type')->pluck('subject_type')
                        ->mapWithKeys(fn (string $type) => [$type => self::subjectLabel($type)])->all()),
                SelectFilter::make('causer_id')
                    ->label('Autor')
                    ->options(fn () => User::whereIn('id', Activity::query()->select('causer_id')->whereNotNull('causer_id'))
                        ->orderBy('name')->pluck('name', 'id')->all()),
                Filter::make('created_at')
                    ->label('Fecha')
                    ->schema([
                        DatePicker::make('from')->label('Desde'),
                        DatePicker::make('until')->label('Hasta'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageActivities::route('/'),
        ];
    }

    /**
     * KeyValueEntry muestra escalares: los valores anidados se serializan a JSON.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private static function flatten(array $data): array
    {
        return collect($data)
            ->map(fn ($value) => is_scalar($value) || $value === null
                ? (string) $value
                : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            ->all();
    }
}
