<?php

declare(strict_types=1);

use BBSLab\FilamentPasswordRotation\Filament\Pages\ForcePasswordChange;
use BBSLab\FilamentPasswordRotation\FilamentPasswordRotationPlugin;
use BBSLab\FilamentPasswordRotation\Http\Middleware\EnsurePasswordIsNotExpired;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Workbench\Database\Factories\UserFactory;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');

    config([
        'filament-password-rotation.enabled' => true,
        'filament-password-rotation.days' => 90,
        'filament-password-rotation.warn_days' => 7,
    ]);
});

it('appends the expiry middleware to the panel authenticated stack', function (): void {
    expect(Filament::getPanel('admin')->getAuthMiddleware())
        ->toContain(EnsurePasswordIsNotExpired::class);
});

it('registers the forced-change page route under the panel path', function (): void {
    expect(Route::has('filament.admin.pages.password.rotate'))->toBeTrue()
        ->and(Route::getRoutes()->getByName('filament.admin.pages.password.rotate')->uri())
        ->toBe('admin/password/rotate');
});

it('exposes an id used to build page route names', function (): void {
    expect(FilamentPasswordRotationPlugin::make()->getId())->toBe('filament-password-rotation')
        ->and(ForcePasswordChange::getRouteName(Filament::getPanel('admin')))
        ->toBe('filament.admin.pages.password.rotate');
});

it('shows the expiry callout on a panel page for a user nearing expiry', function (): void {
    // The PAGE_START render hook (registered in boot()) fires when the panel
    // renders a page; a user nearing expiry sees the callout.
    $user = UserFactory::new()->create(['password_changed_at' => now()->subDays(85)]);

    $this->actingAs($user)->get('/admin')
        ->assertOk()
        ->assertSee(__('filament-password-rotation::messages.warning_heading'));
});

it('shows no expiry callout on a panel page for a fresh password', function (): void {
    $user = UserFactory::new()->create(['password_changed_at' => now()]);

    $this->actingAs($user)->get('/admin')
        ->assertOk()
        ->assertDontSee(__('filament-password-rotation::messages.warning_heading'));
});

it('registers the expiry gate as persistent Livewire middleware', function (): void {
    // isPersistent: true so the gate also re-runs on /livewire/update, not only
    // on full-page GETs — otherwise an expired session could keep driving in-page
    // Livewire actions without ever being forced to rotate.
    expect(Livewire::getPersistentMiddleware())->toContain(EnsurePasswordIsNotExpired::class);
});

it('redirects an expired user off a panel page to the forced-change screen', function (): void {
    $user = UserFactory::new()->create(['password_changed_at' => now()->subDays(120)]);

    $this->actingAs($user)->get('/admin')
        ->assertRedirect(ForcePasswordChange::getUrl(panel: 'admin'));
});

it('lets an expired user reach the forced-change page without a redirect loop', function (): void {
    $user = UserFactory::new()->create(['password_changed_at' => now()->subDays(120)]);

    $this->actingAs($user)->get(ForcePasswordChange::getUrl(panel: 'admin'))
        ->assertOk();
});
