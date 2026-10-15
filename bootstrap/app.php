<?php

use App\Http\Middleware\PublicCache;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Se registra al final: sus patrones /{pais}/… no deben tapar ninguna otra ruta.
        then: fn () => Route::middleware('public')->group(base_path('routes/public.php')),
    )
    // Los comandos del importador viven en su dominio (ARQUITECTURA.md §9.2).
    ->withCommands([
        __DIR__.'/../app/Domain/Import/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // Páginas públicas: sin sesión, sin cookies y sin CSRF, para que el CDN las
        // cachee enteras (AGENTS.md, regla 2). No usar el grupo 'web' aquí.
        $middleware->group('public', [
            SubstituteBindings::class,
            PublicCache::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
