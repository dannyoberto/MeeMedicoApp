<?php

namespace App\Domain\Directory\Cdn;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class PurgeCdnPaths implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    /**
     * @param  array<int, string>  $paths
     */
    public function __construct(public readonly array $paths)
    {
        // Si la operación que la origina se revierte, no se purga nada.
        $this->afterCommit();
    }

    public function handle(CdnPurger $purger): void
    {
        $purger->purge($this->paths);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }
}
