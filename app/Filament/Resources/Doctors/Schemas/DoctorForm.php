<?php

namespace App\Filament\Resources\Doctors\Schemas;

use App\Domain\Directory\Enums\DoctorGender;
use App\Domain\Directory\Enums\LicenseSource;
use App\Domain\Directory\Support\NameNormalizer;
use App\Domain\Directory\Support\SuppressionCheck;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

/**
 * Crear: datos mínimos (CreateDoctorAction); el agregado se completa al editar.
 * Editar: Datos + Perfil (UpdateDoctorAction) y la lista de requisitos para publicar.
 * El estado, la verificación y el slug no son campos: cambian con acciones auditadas.
 */
class DoctorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Tabs::make()
                    ->columnSpan(['lg' => 2])
                    ->tabs([
                        Tab::make('Datos')->schema(self::identityFields()),
                        Tab::make('Perfil')
                            ->visibleOn('edit')
                            ->schema([
                                TextInput::make('headline')
                                    ->label('Titular')
                                    ->helperText('Una línea bajo el nombre: "Cardiólogo intervencionista con 15 años de experiencia".')
                                    ->maxLength(255),
                                Textarea::make('bio')->label('Biografía')->rows(5),
                                Textarea::make('education')->label('Formación')->rows(4),
                                Textarea::make('experience')->label('Experiencia')->rows(4),
                            ]),
                    ]),
                Section::make('Requisitos para publicar')
                    ->visibleOn('edit')
                    ->columnSpan(['lg' => 1])
                    ->schema([
                        View::make('filament.admin.doctors.publication-checklist'),
                    ]),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    private static function identityFields(): array
    {
        return [
            Select::make('country_id')
                ->label('País')
                ->relationship('country', 'name')
                ->required()
                ->live()
                // El país fija la URL /{pais}/medicos/{slug} y el ámbito de la licencia.
                ->disabled(fn (string $operation) => $operation !== 'create'),
            Grid::make(2)->schema([
                TextInput::make('first_name')->label('Nombres')->required()->maxLength(100)->live(onBlur: true),
                TextInput::make('last_name')->label('Apellidos')->required()->maxLength(150)->live(onBlur: true),
            ]),
            TextInput::make('professional_name')
                ->label('Nombre profesional')
                ->helperText('Opcional: cómo se presenta ("Dra. Ana Rojas"). Si está vacío se usan nombres y apellidos.')
                ->maxLength(200),
            Select::make('gender')->label('Género')->options(DoctorGender::class),
            Grid::make(2)->schema([
                TextInput::make('license_number')
                    ->label('Número de colegiado')
                    ->helperText('Único por país. Cambiarlo en una ficha verificada retira la verificación.')
                    ->maxLength(100),
                Select::make('license_source')
                    ->label('Origen de la licencia')
                    ->options(LicenseSource::class)
                    ->default(LicenseSource::Admin),
            ]),
            // Solo aparece si hay una supresión con este nombre: el nombre solo es una clave débil.
            Checkbox::make('confirm_homonym')
                ->label('Confirmo que es otra persona (homónimo de una supresión registrada)')
                ->dehydrated()
                ->visible(fn (Get $get, string $operation) => $operation === 'create'
                    && filled($get('country_id'))
                    && SuppressionCheck::nameMatch($get('country_id'), NameNormalizer::normalize("{$get('first_name')} {$get('last_name')}")) !== null),
        ];
    }
}
