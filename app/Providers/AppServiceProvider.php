<?php

namespace App\Providers;

use App\Domain\Directory\Cdn\CdnPurger;
use App\Domain\Geo\Enums\CountryStatus;
use App\Domain\Search\DoctorSearchService;
use App\Domain\Search\PostgresDoctorSearch;
use App\Filament\Support\PanelDefaults;
use App\Models\Country;
use App\Policies\ActivityPolicy;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
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

        // Motor de búsqueda pública (ARQUITECTURA.md §7). Meilisearch en Fase 2 es otro driver.
        $this->app->bind(DoctorSearchService::class, PostgresDoctorSearch::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Solo presentación: Filament convierte desde UTC al mostrar y de vuelta al guardar.
        FilamentTimezone::set(config('app.display_timezone'));
        PanelDefaults::register();

        // El modelo de auditoría es del paquete: no entra en el descubrimiento automático.
        Gate::policy(Activity::class, ActivityPolicy::class);

        // /{pais}/… en el sitio público y en /api/v1: solo países activos. Un país se
        // activa cuando su carga de datos está lista (DATABASE.md §7.1).
        Route::bind('pais', fn (string $slug) => Country::where('slug', $slug)
            ->where('status', CountryStatus::Active)
            ->firstOrFail());
    }
}
