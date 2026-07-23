<?php

declare(strict_types=1);

use BBSLab\FilamentPasswordRotation\Filament\ExpiryCallout;
use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User;
use Workbench\Database\Factories\UserFactory;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');

    config([
        'laravel-password-rotation.enabled' => true,
        'laravel-password-rotation.days' => 90,
        'laravel-password-rotation.warn_days' => 7,
        'laravel-password-rotation.force_on_first_login' => true,
    ]);
});

function callout(): ?string
{
    return (new ExpiryCallout)->render()?->render();
}

it('renders a callout carrying the expiry date for a user nearing expiry', function (): void {
    $user = UserFactory::new()->create(['password_changed_at' => now()->subDays(85)]);
    $this->actingAs($user);

    expect(callout())
        ->toContain(__('filament-password-rotation::messages.warning_heading'))
        ->toContain($user->passwordExpiresAt()->toFormattedDateString())
        ->toContain('padding-top'); // top gutter so the banner is not flush with the topbar
});

it('renders nothing when nobody is authenticated', function (): void {
    expect(callout())->toBeNull();
});

it('renders nothing when the feature is disabled', function (): void {
    config(['laravel-password-rotation.enabled' => false]);
    $this->actingAs(UserFactory::new()->create(['password_changed_at' => now()->subDays(85)]));

    expect(callout())->toBeNull();
});

it('renders nothing when the warning window is disabled', function (): void {
    config(['laravel-password-rotation.warn_days' => 0]);
    $this->actingAs(UserFactory::new()->create(['password_changed_at' => now()->subDays(85)]));

    expect(callout())->toBeNull();
});

it('renders nothing for a user that does not implement the interface', function (): void {
    $this->actingAs(new class extends User {});

    expect(callout())->toBeNull();
});

it('renders nothing for an already expired user (they are redirected instead)', function (): void {
    $this->actingAs(UserFactory::new()->create(['password_changed_at' => now()->subDays(120)]));

    expect(callout())->toBeNull();
});

it('renders nothing outside the warning window', function (): void {
    $this->actingAs(UserFactory::new()->create(['password_changed_at' => now()]));

    expect(callout())->toBeNull();
});

it('renders at the exact moment the warning window opens', function (): void {
    // days=90, warn_days=7 → the window opens at day 83 (expiresAt - warn_days == now).
    // Freezing on a whole second keeps the timestamp exact across the DB round-trip.
    $this->freezeTime();
    $this->travelTo(now()->startOfSecond());
    $this->actingAs(UserFactory::new()->create(['password_changed_at' => now()->subDays(83)]));

    expect(callout())->not->toBeNull();
});

it('renders nothing the day before the warning window opens', function (): void {
    $this->freezeTime();
    $this->travelTo(now()->startOfSecond());
    $this->actingAs(UserFactory::new()->create(['password_changed_at' => now()->subDays(82)]));

    expect(callout())->toBeNull();
});

it('renders nothing when the expiry date cannot be determined', function (): void {
    config(['laravel-password-rotation.force_on_first_login' => false]);

    $user = UserFactory::new()->create();
    $user->forceFill(['password_changed_at' => null])->saveQuietly();
    $this->actingAs($user);

    expect(callout())->toBeNull();
});
