<?php

namespace App\Domain\Directory\Cdn;

use Illuminate\Support\Facades\Log;

/**
 * Driver sin CDN: registra las rutas que se habrían purgado. Permite verificar qué
 * invalida cada operación antes de tener Cloudflare delante.
 */
class LogCdnPurger implements CdnPurger
{
    public function purge(array $paths): void
    {
        if ($paths === []) {
            return;
        }

        Log::info('cdn.purge', ['paths' => array_values(array_unique($paths))]);
    }
}
