<?php

namespace App\Filament\Resources\Activities;

use App\Filament\Resources\Activities\Pages\ManageActivities;
use App\Filament\Support\ActivityPresenter;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

/**
 * Visor de la auditoría (DATABASE.md §14.4, MODELO-IDENTIDAD.md §10). Solo lectura:
 * es el único registro que permite deshacer a mano una fusión equivocada.
 *
 * Es la tabla que más crece: paginación simple (sin COUNT) y filtros con opciones
 * fijas, nunca un DISTINCT sobre toda la tabla.
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
        'App\Models\Location' => 'Ubicación',
        'App\Models\Language' => 'Idioma',
        'App\Models\ImportBatch' => 'Lote de importación',
        'App\Models\ImportRow' => 'Fila de importación',
    ];

    public static function subjectLabel(?string $type): string
    {
        return $type ? (self::SUBJECTS[$type] ?? class_basename($type)) : '—';
    }

    /**
     * Enlace a la pantalla del sujeto, si tiene una.
     */
    public static function subjectUrl(Activity $activity): ?string
    {
        if (blank($activity->subject_type) || blank($activity->subject_id)) {
            return null;
        }

        $resource = Filament::getModelResource($activity->subject_type);

        foreach (['view', 'edit'] as $page) {
            if ($resource && $resource::hasPage($page)) {
                return $resource::getUrl($page, ['record' => $activity->subject_id]);
            }
        }

        return null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('created_at')->label('Fecha')->dateTime(),
                        TextEntry::make('event')->label('Evento')->badge()->color('gray')
                            ->formatStateUsing(fn (?string $state) => ActivityPresenter::eventLabel($state)),
                        TextEntry::make('causer.name')->label('Autor')->placeholder('Sistema / consola'),
                        TextEntry::make('subject_type')->label('Sujeto')
                            ->formatStateUsing(fn (?string $state) => self::subjectLabel($state))
                            ->url(fn (Activity $record) => self::subjectUrl($record)),
                        TextEntry::make('subject_id')->label('ID del sujeto')->copyable()->placeholder('—'),
                        TextEntry::make('summary')->label('Resumen')
                            ->state(fn (Activity $record) => ActivityPresenter::summary($record))
                            ->placeholder('—'),
                    ]),
                Section::make('Cambios')
                    ->visible(fn (Activity $record) => ActivityPresenter::changes($record) !== [])
                    ->schema([
                        RepeatableEntry::make('changes')
                            ->hiddenLabel()
                            ->state(fn (Activity $record) => ActivityPresenter::changes($record))
                            ->table([
                                RepeatableEntry\TableColumn::make('Campo'),
                                RepeatableEntry\TableColumn::make('Antes'),
                                RepeatableEntry\TableColumn::make('Después'),
                            ])
                            ->schema([
                                TextEntry::make('field'),
                                TextEntry::make('old')->color('gray'),
                                TextEntry::make('new'),
                            ]),
                    ]),
                Section::make('Datos registrados')
                    ->collapsible()
                    ->collapsed(fn (Activity $record) => ActivityPresenter::changes($record) !== [])
                    ->schema([
                        KeyValueEntry::make('properties')
                            ->hiddenLabel()
                            ->keyLabel('Clave')
                            ->valueLabel('Valor')
                            ->state(fn (Activity $record) => self::flatten($record->properties?->all() ?? []))
                            ->placeholder('Sin propiedades'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('causer'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('event')
                    ->label('Evento')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state) => ActivityPresenter::eventLabel($state)),
                TextColumn::make('summary')
                    ->label('Resumen')
                    ->state(fn (Activity $record) => ActivityPresenter::summary($record))
                    ->limit(80)
                    ->placeholder('—'),
                TextColumn::make('subject_type')
                    ->label('Sujeto')
                    ->formatStateUsing(fn (?string $state) => self::subjectLabel($state))
                    ->url(fn (Activity $record) => self::subjectUrl($record)),
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
            ->paginationMode(PaginationMode::Simple)
            ->persistFiltersInSession()
            ->filters([
                SelectFilter::make('event')
                    ->label('Evento')
                    ->options(ActivityPresenter::EVENTS)
                    ->searchable(),
                SelectFilter::make('subject_type')
                    ->label('Tipo de sujeto')
                    ->options(self::SUBJECTS),
                Filter::make('causer')
                    ->label('Autor')
                    ->schema([
                        Select::make('causer_id')
                            ->label('Autor')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => User::query()
                                ->where(fn ($q) => $q->where('name', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%"))
                                ->orderBy('name')->limit(20)->pluck('name', 'id')->all())
                            ->getOptionLabelUsing(fn ($value) => User::find($value)?->name),
                    ])
                    ->query(fn (Builder $query, array $data) => $query->when($data['causer_id'] ?? null,
                        fn (Builder $q, string $id) => $q->where('causer_type', User::class)->where('causer_id', $id)))
                    ->indicateUsing(fn (array $data) => filled($data['causer_id'] ?? null)
                        ? 'Autor: '.User::find($data['causer_id'])?->name
                        : null),
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
                ViewAction::make()->slideOver(),
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
