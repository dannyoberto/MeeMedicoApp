<?php

namespace App\Filament\Support;

use Illuminate\Support\Str;

/**
 * Títulos y migas en español: solo la primera letra en mayúscula ("Lotes de importación").
 * Filament usa ucwords ("Lotes De Importación"), y desactivarlo deja todo en minúscula.
 * Solo hace falta en recursos con etiquetas de varias palabras.
 */
trait SentenceCaseLabels
{
    public static function getTitleCaseModelLabel(): string
    {
        return Str::ucfirst(static::getModelLabel());
    }

    public static function getTitleCasePluralModelLabel(): string
    {
        return Str::ucfirst(static::getPluralModelLabel());
    }
}
