<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Support\BrandColors;
use App\Filament\Support\InitialsAvatarProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // Backoffice en su propio hostname (ARQUITECTURA.md §9.1): Cloudflare aplica
        // caché y WAF por hostname, y /admin no compite con el prefijo /{pais}/.
        // Sin ADMIN_DOMAIN cae en /admin, solo como respaldo para desarrollo.
        $domain = config('app.admin_domain');

        if ($domain) {
            $panel->domain($domain)->path('');
        } else {
            $panel->path('admin');
        }

        $this->brand($panel);

        return $panel
            ->default()
            ->id('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            // Recuperar contraseña y definirla desde la invitación (SendPasswordSetupLinkAction).
            ->passwordReset()
            // Escalas exactas de tokens.css (design-system.md §3.1 y §12.1).
            // La fuente no se configura: Filament 5 ya sirve Inter autoalojada por defecto.
            ->colors(BrandColors::all())
            // Avatar de iniciales local: el proveedor por defecto envía los nombres a ui-avatars.com.
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            // Estructura del mockup de referencia (docs/Backoffice · Panel …png).
            ->navigationGroups([
                NavigationGroup::make('Directorio'),
                NavigationGroup::make('Operación'),
                NavigationGroup::make('Plataforma'),
            ])
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn () => view('filament.admin.user-summary'))
            // Campana: avisos de procesos en cola (lotes, publicación en lote). Tabla notifications.
            ->databaseNotifications()
            // Navegación sin recargar la página; avisa antes de perder un formulario sin guardar.
            ->spa()
            ->unsavedChangesAlerts()
            // Más ancho para las tablas grandes cuando hace falta.
            ->sidebarCollapsibleOnDesktop()
            // La búsqueda global solo cubre Médicos y Usuarios; los catálogos tienen su tabla.
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * Logo, etiqueta "Admin" y favicon (design-system.md §13). Sin los archivos de
     * public/images/brand/, el panel muestra "MeeMedico" como texto.
     */
    private function brand(Panel $panel): void
    {
        $panel->brandName('MeeMedico')->brandLogoHeight('2rem');

        if (file_exists(public_path('images/brand/logo-horizontal-ink.svg')) && file_exists(public_path('images/brand/logo.png'))) {
            // La vista alterna tinta (claro) y oro (oscuro): el oro no se usa sobre blanco.
            $panel->brandLogo(fn () => view('filament.admin.brand'));
        }

        if (file_exists(public_path('images/brand/isotipo.svg'))) {
            $panel->favicon(asset('images/brand/isotipo.svg'));
        }
    }
}
