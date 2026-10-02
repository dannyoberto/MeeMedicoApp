<?php

namespace App\Filament\Forms;

use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Un mismo formulario de recurso puede verse en su página (crear o editar) o en un modal
 * (alta o edición desde la lista). En la página va en una tarjeta; en el modal los campos
 * van solos y a todo el ancho, porque el modal ya es el contenedor. Sin esto, el modal
 * mostraba una tarjeta dentro de otra, ocupando media anchura (design-system.md §12.4).
 */
final class FormLayout
{
    /**
     * @param  array<int, mixed>  $fields
     */
    public static function configure(Schema $schema, array $fields, int $columns = 2): Schema
    {
        $livewire = $schema->getLivewire();

        if ($livewire instanceof CreateRecord || $livewire instanceof EditRecord) {
            return $schema->components([
                Section::make()->columns($columns)->schema($fields),
            ]);
        }

        return $schema->columns($columns)->components($fields);
    }
}
