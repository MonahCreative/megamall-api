<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use App\Livewire\NewOrderAlert;
use App\Support\Theme;
use Filament\View\PanelsRenderHook;
use Livewire\Livewire;
use Filament\Support\Colors\Color;
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
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('Mega Mall Admin')
            // The admin follows the shop's main colour from Store settings (read on each request).
            ->colors(fn (): array => [
                'primary' => Color::hex(Theme::colours()['theme_primary']),
                'gray' => Color::Slate,
            ])
            ->font('Outfit')
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                NavigationGroup::make('Orders & POS'),
                NavigationGroup::make('Reports'),
                NavigationGroup::make('Products & Stock'),
                NavigationGroup::make('Promo'),
                NavigationGroup::make('Delivery'),
                NavigationGroup::make('Store'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            // Our own dashboard (app/Filament/Pages/Dashboard.php) is found automatically.
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // New-order pop-up and chime on every admin page (only for logged-in staff).
            ->renderHook(PanelsRenderHook::BODY_END, fn (): string => auth()->user()?->is_admin ? Livewire::mount(NewOrderAlert::class) : '')
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
}
