<?php

declare(strict_types=1);

use BBSLab\FilamentPasswordRotation\Filament\Pages\ForcePasswordChange;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Notification;
use LogicException;
use Workbench\App\Models\User;
use Workbench\Database\Factories\UserFactory;

use function Pest\Livewire\livewire;

beforeEach(fn () => Filament::setCurrentPanel('admin'));

function resetUser(): User
{
    $user = UserFactory::new()->create(['password_changed_at' => now()->subDays(100)]);

    return User::query()->findOrFail($user->getKey());
}

it('renders the reset card with no password fields when expiry_action is reset', function (): void {
    config(['filament-password-rotation.expiry_action' => 'reset']);

    $this->actingAs(resetUser());

    livewire(ForcePasswordChange::class)
        ->assertSee(__('filament-password-rotation::messages.reset_intro'))
        ->assertSee(__('filament-password-rotation::messages.reset_submit'))
        ->assertDontSee(__('filament-password-rotation::messages.new_password'))
        ->assertDontSee(__('filament-password-rotation::messages.current_password'))
        // The card submits to the reset handler, not the password-change one.
        ->assertSeeHtml('wire:submit="sendReset"');
});

it('still renders the change form when expiry_action is change (default)', function (): void {
    config(['filament-password-rotation.expiry_action' => 'change']);

    $this->actingAs(resetUser());

    livewire(ForcePasswordChange::class)
        ->assertSee(__('filament-password-rotation::messages.new_password'))
        ->assertSee(__('filament-password-rotation::messages.submit'))
        ->assertDontSee(__('filament-password-rotation::messages.reset_submit'));
});

it('emails a Filament reset link, logs the user out, toasts and redirects to login on submit', function (): void {
    config(['filament-password-rotation.expiry_action' => 'reset']);

    $user = resetUser();
    $this->actingAs($user);

    Notification::fake();

    // A probe the guard's logout leaves alone but session()->invalidate() flushes.
    session(['rotation_probe' => 'present']);

    livewire(ForcePasswordChange::class)
        ->call('sendReset')
        ->assertNotified(__('filament-password-rotation::messages.reset_sent'))
        ->assertRedirect(Filament::getLoginUrl());

    // The reset notification actually went out, and its link points at the
    // panel's own reset page — NOT the default route('password.reset') that a
    // Filament app does not define (the bug this guards against). Building that
    // URL is the step that used to throw, so the send happening at all proves it.
    Notification::assertSentTo(
        $user,
        ResetPasswordNotification::class,
        fn (ResetPasswordNotification $notification): bool => str_contains($notification->url, '/admin/')
            && str_contains($notification->url, 'reset'),
    );

    expect(Filament::auth()->check())->toBeFalse()   // guard signed out
        ->and(session()->has('rotation_probe'))->toBeFalse() // session invalidated
        ->and(session()->token())->not->toBeEmpty(); // CSRF token regenerated on the fresh session
});

it('refuses to send a reset link when the panel has no password reset enabled', function (): void {
    config(['filament-password-rotation.expiry_action' => 'reset']);

    // A panel that never enabled ->passwordReset() has no reset route, so
    // sendReset() must fail loudly rather than build a broken link.
    filament()->registerPanel(Panel::make()->id('no-reset'));
    Filament::setCurrentPanel('no-reset');

    $this->actingAs(resetUser());

    livewire(ForcePasswordChange::class)->call('sendReset');
})->throws(LogicException::class);
