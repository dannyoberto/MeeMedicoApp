<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * Convenciones de presentación comunes a todas las tablas y esquemas del backoffice
 * (design-system.md §12.5). Se fijan una vez aquí en lugar de repetirse en cada recurso.
 */
final class PanelDefaults
{
    /** "14 oct. 2026": día primero, como se lee en español. Filament usa "oct. 14, 2026". */
    private const DATE = 'j M Y';

    private const DATE_TIME = 'j M Y, H:i';

    public static function register(): void
    {
        Table::configureUsing(fn (Table $table) => $table
            ->defaultDateDisplayFormat(self::DATE)
            ->defaultDateTimeDisplayFormat(self::DATE_TIME)
            ->modifyUngroupedRecordActionsUsing(fn (Action $action) => self::compactRowAction($action)));

        Schema::configureUsing(fn (Schema $schema) => $schema
            ->defaultDateDisplayFormat(self::DATE)
            ->defaultDateTimeDisplayFormat(self::DATE_TIME));
    }

    /**
     * Las acciones de fila se repiten en cada registro: solo icono, con el texto en un
     * tooltip (teclado y ratón) y en aria-label (Filament lo toma del label). Un botón de
     * icono también da un área de clic mayor que el enlace de texto (20 px de alto).
     * Las acciones que piden ->button() a propósito (una cola con un único verbo) se respetan.
     * Las de cabecera, menús y lotes no pasan por aquí: llevan texto porque tienen consecuencias.
     */
    private static function compactRowAction(Action $action): void
    {
        if ($action->getView() !== $action::LINK_VIEW) {
            return;
        }

        $action
            ->iconButton()
            ->tooltip(fn (Action $action) => $action->getLabel());
    }
}
