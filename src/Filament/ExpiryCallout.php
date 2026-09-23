<?php

declare(strict_types=1);

namespace BBSLab\FilamentPasswordRotation\Filament;

use BBSLab\LaravelPasswordRotation\Contracts\MustRotatePassword;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFactory;

/**
 * Builds the "your password expires soon" callout injected into the panel via a
 * render hook. Returns null (nothing rendered) unless the authenticated user
 * implements MustRotatePassword and is inside the warning window.
 */
class ExpiryCallout
{
    public function render(): ?View
    {
        $user = Filament::auth()->user();

        // Expired users are redirected to the change screen by the middleware, so
        // the callout only nudges a still-valid password nearing expiry.
        if (! $user instanceof MustRotatePassword || ! $user->passwordIsExpiring()) {
            return null;
        }

        // The package view namespace is registered at runtime, so PHPStan cannot
        // resolve it to a view-string; the view genuinely exists.
        /** @var view-string $view */
        $view = 'filament-password-rotation::expiry-callout';

        return ViewFactory::make($view, [
            'expiresAt' => $user->passwordExpiresAt(),
        ]);
    }
}
