<?php

use App\Http\Controllers\Api\V1\ContactEventController;
use App\Http\Controllers\Api\V1\SuggestController;
use Illuminate\Support\Facades\Route;

/*
| /api/v1: las islas de React hoy y la app móvil en V2 (ARQUITECTURA.md §9.3). Llaman a
| las mismas Actions y servicios que las páginas. El grupo 'api' no usa sesión.
| {pais} se resuelve en AppServiceProvider: solo países activos.
*/

Route::prefix('v1')->group(function () {
    Route::get('{pais}/suggest', SuggestController::class)
        ->where('pais', '[a-z]+(?:-[a-z]+)*')
        ->middleware('throttle:120,1')
        ->name('api.v1.suggest');

    Route::post('contact-events', ContactEventController::class)
        ->middleware('throttle:30,1')
        ->name('api.v1.contact-events');
});
