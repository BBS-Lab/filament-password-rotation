<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Rotation settings
    |--------------------------------------------------------------------------
    |
    | The generic rotation settings (enabled, days, column, history, warnings,
    | first-login, models, ...) live in the shared base package and are read
    | from config('laravel-password-rotation.*'). Publish/edit them there:
    |
    |     php artisan vendor:publish --tag=laravel-password-rotation-config
    |
    | Only the Filament-specific options below belong to this package.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Forced-change page slug
    |--------------------------------------------------------------------------
    |
    | The forced-change page is registered under the panel path at
    | "{panel}/{slug}". A "/" in the slug becomes a "." in the route name, e.g.
    | "password/rotate" => filament.{panel}.pages.password.rotate. Change it
    | only if it collides with one of your own routes.
    |
    */

    'slug' => env('PASSWORD_ROTATION_SLUG', 'password/rotate'),

    /*
    |--------------------------------------------------------------------------
    | Expiry action
    |--------------------------------------------------------------------------
    |
    | What the forced-change page does when a user's password has expired:
    |
    |   "change" - (default) show the in-panel form so the user picks a new
    |              password there and then.
    |   "reset"  - show a single button that emails a password reset link,
    |              signs the user out and sends them to the login page. Use
    |              this when your reset flow (e.g. a custom broker or SSO) must
    |              own the actual password change.
    |
    | The "reset" action requires the authenticatable to implement
    | Illuminate\Contracts\Auth\CanResetPassword (Laravel's default User does).
    |
    */

    'expiry_action' => env('PASSWORD_ROTATION_EXPIRY_ACTION', 'change'),

];
