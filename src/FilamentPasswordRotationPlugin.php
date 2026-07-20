<?php

declare(strict_types=1);

namespace BBSLab\FilamentPasswordRotation;

use BBSLab\FilamentPasswordRotation\Filament\Pages\ForcePasswordChange;
use BBSLab\FilamentPasswordRotation\Http\Middleware\EnsurePasswordIsNotExpired;
use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentPasswordRotationPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'filament-password-rotation';
    }

    public function register(Panel $panel): void
    {
        // Registration is unconditional so the forced-change route always exists
        // (it must be compiled before routes cache, before config-ordering is
        // guaranteed). The config('enabled') gate lives in the middleware, which
        // runs per request once config is fully loaded.
        // isPersistent: true so the gate also re-runs on Livewire's
        // /livewire/update endpoint (like Filament's own Authenticate). Without
        // it, a session that predates expiry could keep driving in-page Livewire
        // actions on an already-loaded page without ever being forced to rotate.
        $panel
            ->authMiddleware([EnsurePasswordIsNotExpired::class], isPersistent: true)
            ->pages([ForcePasswordChange::class]);
    }

    public function boot(Panel $panel): void
    {
        // No-op: the expiry callout render hook is registered globally in the service provider.
    }
}
