<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Panel del backoffice. Composición del mockup de referencia con métricas de
 * Fase 1 solamente: sin premium, reviews, clínicas ni SEO (AGENTS.md §7).
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Panel';

    public function getSubheading(): ?string
    {
        return 'Resumen del directorio';
    }

    /**
     * Rejilla de 3: el gráfico ocupa 2 columnas y "Médicos por país" 1, como en el mockup.
     */
    public function getColumns(): int|array
    {
        return ['md' => 3];
    }
}
