<?php

declare(strict_types=1);

namespace BBSLab\FilamentPasswordRotation\Http\Middleware;

use BBSLab\FilamentPasswordRotation\Contracts\MustRotatePassword;
use BBSLab\FilamentPasswordRotation\Filament\Pages\ForcePasswordChange;
use Closure;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsNotExpired
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('filament-password-rotation.enabled')) {
            return $next($request);
        }

        $user = Filament::auth()->user();
        $panel = Filament::getCurrentOrDefaultPanel();

        if (! $user instanceof MustRotatePassword || ! $panel instanceof Panel) {
            return $next($request);
        }

        // Never trap the user on the forced-change page itself or on the way
        // out: the page lives inside the authenticated middleware group, so
        // without this guard the redirect would loop and logout would break.
        if ($request->routeIs(ForcePasswordChange::getRouteName($panel)) || $request->routeIs($panel->generateRouteName('auth.logout'))) {
            return $next($request);
        }

        if ($user->passwordHasExpired()) {
            return redirect()->to(ForcePasswordChange::getUrl(panel: $panel->getId()));
        }

        // A still-valid password nearing expiry is nudged via the PAGE_START
        // callout render hook (see FilamentPasswordRotationPlugin), not here.
        return $next($request);
    }
}
