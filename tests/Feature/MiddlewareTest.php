<?php

declare(strict_types=1);

use BBSLab\FilamentPasswordRotation\Http\Middleware\EnsurePasswordIsNotExpired;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Symfony\Component\HttpFoundation\Response;
use Workbench\App\Models\User;
use Workbench\Database\Factories\UserFactory;

beforeEach(fn () => Filament::setCurrentPanel('admin'));

function runMiddleware(?Request $request = null): Response
{
    $request ??= Request::create('/admin/dashboard');

    return (new EnsurePasswordIsNotExpired)->handle($request, fn (): Response => new Response('next'));
}

/** A request bound to a named route so the middleware's routeIs() skip logic fires. */
function requestForRoute(string $name, string $uri): Request
{
    $request = Request::create($uri);
    $route = (new RoutingRoute('GET', $uri, []))->name($name);
    $request->setRouteResolver(fn (): RoutingRoute => $route);

    return $request;
}

function expiredUser(): User
{
    $user = UserFactory::new()->create(['password_changed_at' => now()->subDays(100)]);

    return User::query()->findOrFail($user->getKey());
}

it('is inert when the feature is disabled', function (): void {
    config(['filament-password-rotation.enabled' => false]);

    $this->actingAs(expiredUser());

    expect(runMiddleware()->getContent())->toBe('next');
});

it('ignores users that do not implement the interface', function (): void {
    // A plain authenticatable that does NOT implement MustRotatePassword.
    $this->actingAs(new class extends Illuminate\Foundation\Auth\User {});

    expect(runMiddleware()->getContent())->toBe('next');
});

it('lets a still-valid user through', function (): void {
    config(['filament-password-rotation.warn_days' => 0]); // keep the warn path out of scope

    $user = UserFactory::new()->create(['password_changed_at' => now()]);
    $this->actingAs(User::query()->findOrFail($user->getKey()));

    expect(runMiddleware()->getContent())->toBe('next');
});

it('redirects an expired user to the forced-change screen', function (): void {
    $this->actingAs(expiredUser());

    $response = runMiddleware();

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getTargetUrl())->toContain('admin/password/rotate');
});

it('never traps the user on the forced-change page route', function (): void {
    $this->actingAs(expiredUser());

    $request = requestForRoute('filament.admin.pages.password.rotate', '/admin/password/rotate');

    expect(runMiddleware($request)->getContent())->toBe('next');
});

it('never traps the user on the way out (logout)', function (): void {
    $this->actingAs(expiredUser());

    $request = requestForRoute('filament.admin.auth.logout', '/admin/logout');

    expect(runMiddleware($request)->getContent())->toBe('next');
});
