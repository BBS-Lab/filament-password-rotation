<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use BBSLab\FilamentPasswordRotation\FilamentPasswordRotationPlugin;
use BBSLab\LaravelPasswordRotation\Contracts\MustRotatePassword;
use BBSLab\LaravelPasswordRotation\Facades\PasswordRotation;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
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
            ->passwordReset()
            ->profile()
            ->pages([
                Dashboard::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                class_exists(PreventRequestForgery::class) ? PreventRequestForgery::class : VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugin(FilamentPasswordRotationPlugin::make());
    }

    public function boot(): void
    {
        // Demo of the bypass hook: exempt SSO-provisioned accounts from the forced
        // rotation. The callback receives the request and the expired user; here it
        // reads the seeded `is_sso` flag. A real app might instead read a session
        // attribute set at SSO login: fn ($request) => $request->session()->get('sso').
        PasswordRotation::bypass(
            fn (Request $request, MustRotatePassword $user): bool => $user->is_sso === true,
        );
    }
}
