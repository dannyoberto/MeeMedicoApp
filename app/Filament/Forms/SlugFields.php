<?php

namespace App\Filament\Forms;

use App\Domain\Directory\Support\SlugRules;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

/**
 * Par nombre + slug para entidades con URL pública.
 *
 * - Al crear: el slug se propone desde el nombre y se valida con SlugRules.
 * - Al editar: el slug se muestra bloqueado; se cambia solo con ChangeSlugAction,
 *   que deja el 301. Nunca se guarda desde el formulario de edición.
 */
final class SlugFields
{
    /**
     * @param  class-string  $modelClass
     * @return array<TextInput>
     */
    public static function make(string $modelClass, int $nameMaxLength = 150): array
    {
        return [
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength($nameMaxLength)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Set $set, ?string $state, string $operation) {
                    if ($operation === 'create') {
                        $set('slug', Str::slug((string) $state));
                    }
                }),

            TextInput::make('slug')
                ->label('Slug (URL)')
                ->helperText(fn (string $operation) => $operation === 'create'
                    ? 'Se genera desde el nombre. Revísalo: forma parte de la URL pública.'
                    : 'Para cambiarlo usa "Cambiar slug": deja una redirección 301.')
                ->required()
                ->disabled(fn (string $operation) => $operation !== 'create')
                ->dehydrated(fn (string $operation) => $operation === 'create')
                ->rule(fn (Get $get, string $operation) => function (string $attribute, mixed $value, Closure $fail) use ($get, $operation, $modelClass) {
                    if ($operation !== 'create') {
                        return;
                    }

                    if ($violation = SlugRules::violation($modelClass, (string) $value, $get('country_id'))) {
                        $fail($violation);
                    }
                }),
        ];
    }
}
