<?php

use App\Filament\Support\ActivityPresenter;

/*
| El backoffice muestra cada evento de auditoría con un nombre legible. Si una Action
| empieza a registrar un evento nuevo, este test obliga a darle nombre en
| ActivityPresenter::EVENTS (y así aparece también en el filtro de la auditoría).
*/

it('todo evento que registra el código tiene nombre en el backoffice', function () {
    $events = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app', FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        $source = $file->getExtension() === 'php' ? file_get_contents($file->getPathname()) : '';

        if (! str_contains($source, 'activity()')) {
            continue;
        }

        // Los eventos son "entidad.accion" en singular; config('import.x') no lo es.
        preg_match_all("/(?<!config\\()'((?:doctor|import|slug|location|catalog|user|role|suppression|claim)\\.[a-z_]+)'/", $source, $matches);
        $events = [...$events, ...$matches[1]];
    }

    expect($events)->not->toBeEmpty()
        ->and(array_diff(array_unique($events), array_keys(ActivityPresenter::EVENTS)))->toBeEmpty();
});
