<?php

namespace App\Domain\Directory\Cdn;

/**
 * Purga de rutas públicas en la CDN. Las Actions no la llaman directamente: despachan
 * PurgeCdnPaths, que corre en cola y solo después del commit (una transacción revertida
 * no debe purgar nada).
 */
interface CdnPurger
{
    /**
     * @param  array<int, string>  $paths  rutas absolutas desde la raíz: /costa-rica/medicos/juan-perez
     */
    public function purge(array $paths): void;
}
