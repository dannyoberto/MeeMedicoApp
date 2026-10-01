<?php

namespace App\Providers;

use App\Domain\Directory\Cdn\CdnPurger;
use App\Policies\ActivityPolicy;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Driver de purga de CDN según config/cdn.php (log hasta que haya Cloudflare).
        $this->app->bind(CdnPurger::class, fn ($app) => $app->make(
            config('cdn.drivers.'.config('cdn.driver')),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Solo presentación: Filament convierte desde UTC al mostrar y de vuelta al guardar.
        FilamentTimezone::set(config('app.display_timezone'));

        // El modelo de auditoría es del paquete: no entra en el descubrimiento automático.
        Gate::policy(Activity::class, ActivityPolicy::class);
    }
}
