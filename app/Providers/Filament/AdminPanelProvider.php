<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->passwordReset()
            ->profile()
            ->tenant(\App\Models\Club::class, slugAttribute: 'slug')
            ->colors(['primary' => Color::Green])
            ->brandName('VoetbalPlanner')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([Pages\Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                function (): string {
                    $club = filament()->getTenant();
                    if (! $club) return '';

                    $vars = [];
                    foreach ([
                        'primary'   => $club->primary_color,
                        'secondary' => $club->secondary_color,
                        'accent'    => $club->accent_color,
                    ] as $name => $hex) {
                        if (! $hex) continue;
                        try {
                            foreach (Color::fromHex($hex) as $shade => $rgb) {
                                $vars[] = "--c-{$name}-{$shade}:{$rgb}";
                            }
                        } catch (\Throwable) {}
                    }

                    return $vars
                        ? '<style>:root{' . implode(';', $vars) . '}</style>'
                        : '';
                }
            )
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn(): string => Blade::render('@if(app(\Lab404\Impersonate\Services\ImpersonateManager::class)->isImpersonating())
                    <div class="flex items-center justify-between gap-4 bg-warning-500 px-6 py-2 text-sm font-medium text-white">
                        <span>
                            &#128065; Je bent ingelogd als <strong>{{ auth()->user()->name }}</strong>
                        </span>
                        <a href="{{ route(\'impersonate.leave\') }}"
                           class="inline-flex items-center gap-1 rounded bg-white/20 px-3 py-1 text-white hover:bg-white/30">
                            &larr; Terug naar eigen account
                        </a>
                    </div>
                @endif'),
            );
    }
}
