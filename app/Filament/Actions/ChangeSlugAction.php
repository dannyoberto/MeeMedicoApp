<?php

namespace App\Filament\Actions;

use App\Domain\Directory\Actions\UpdateSlugAction;
use App\Domain\Directory\Support\SlugRules;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Único camino para cambiar un slug desde el backoffice: delega en UpdateSlugAction,
 * que deja el 301 antes de tocar nada. El formulario de edición muestra el slug bloqueado.
 */
final class ChangeSlugAction
{
    public static function make(): Action
    {
        return Action::make('changeSlug')
            ->label('Cambiar slug')
            ->icon(Heroicon::OutlinedLink)
            ->color('gray')
            ->modalHeading('Cambiar slug')
            ->modalDescription('La URL anterior seguirá funcionando: se registra una redirección 301 hacia la nueva. Úsalo solo para corregir errores; cambiar URLs indexadas cuesta posicionamiento.')
            ->modalSubmitActionLabel('Cambiar')
            ->schema([
                TextInput::make('slug')
                    ->label('Nuevo slug')
                    ->required()
                    ->default(fn (Model $record) => $record->getAttribute('slug'))
                    ->rule(fn (Model $record) => function (string $attribute, mixed $value, Closure $fail) use ($record) {
                        if ($value === $record->getAttribute('slug')) {
                            return;
                        }

                        $violation = SlugRules::violation($record::class, (string) $value, $record->getAttribute('country_id'), $record->getKey());
                        if ($violation) {
                            $fail($violation);
                        }
                    }),
            ])
            ->authorize(fn (Model $record) => auth()->user()?->can('update', $record) ?? false)
            ->action(function (array $data, Model $record, Action $action) {
                try {
                    app(UpdateSlugAction::class)->execute($record, $data['slug']);
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title('Slug no válido')->body($e->validator->errors()->first('slug'))->send();
                    $action->halt();
                }

                Notification::make()->success()->title('Slug actualizado')->body('La URL anterior redirige a la nueva.')->send();
            })
            // En la página de edición, el campo bloqueado debe reflejar el slug nuevo.
            ->after(function (mixed $livewire) {
                if (method_exists($livewire, 'refreshFormData')) {
                    $livewire->refreshFormData(['slug']);
                }
            });
    }
}
