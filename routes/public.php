<?php

use App\Http\Controllers\Public\CountryHomeController;
use App\Http\Controllers\Public\DoctorProfileController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\ListingController;
use App\Http\Controllers\Public\SearchController;
use App\Http\Controllers\Public\SpecialtyController;
use Illuminate\Support\Facades\Route;

/*
| Sitio público (ARQUITECTURA.md §6.2 y §10). Grupo 'public' de bootstrap/app.php:
| sin sesión ni cookies, cacheable entero en el CDN. Blade puro; la interactividad va
| en islas de React que hablan con /api/v1.
|
| El orden importa: las rutas fijas (buscar, medicos) van antes que {especialidad}.
| 'buscar' y 'medicos' están en meemedico.reserved_slugs: ninguna especialidad puede
| llamarse así.
*/

$slug = '[a-z0-9]+(?:-[a-z0-9]+)*';

Route::get('/', HomeController::class)->name('public.home');

Route::prefix('{pais}')
    ->where(['pais' => '[a-z]+(?:-[a-z]+)*', 'especialidad' => $slug, 'ciudad' => $slug, 'slug' => $slug])
    ->group(function () {
        Route::get('/', CountryHomeController::class)->name('public.country');
        Route::get('buscar', SearchController::class)->name('public.search');
        Route::get('medicos/{slug}', DoctorProfileController::class)->name('public.doctor');
        Route::get('{especialidad}', SpecialtyController::class)->name('public.specialty');
        Route::get('{especialidad}/{ciudad}', ListingController::class)->name('public.listing');
    });
