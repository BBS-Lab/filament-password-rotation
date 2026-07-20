<?php

declare(strict_types=1);

use BBSLab\FilamentPasswordRotation\Events\PasswordRotated;
use BBSLab\FilamentPasswordRotation\Filament\Pages\ForcePasswordChange;
use BBSLab\FilamentPasswordRotation\Models\PasswordHistory;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Workbench\App\Models\User;
use Workbench\Database\Factories\UserFactory;

use function Pest\Livewire\livewire;

beforeEach(fn () => Filament::setCurrentPanel('admin'));

/** A reloaded (not "recently created") expired rotatable user. */
function rotatable(array $attributes = []): User
{
    $user = UserFactory::new()->create(array_merge(['password_changed_at' => now()->subDays(100)], $attributes));

    return User::query()->findOrFail($user->getKey());
}

it('serves the forced-change page over HTTP', function (): void {
    $this->actingAs(rotatable());

    $this->get(ForcePasswordChange::getUrl())
        ->assertOk()
        ->assertSee(__('filament-password-rotation::messages.title'));
});

it('throws when the authenticated subject is not an Eloquent model', function (): void {
    $subject = new class implements Authenticatable
    {
        public function getAuthIdentifierName()
        {
            return 'id';
        }

        public function getAuthIdentifier()
        {
            return 1;
        }

        public function getAuthPasswordName()
        {
            return 'password';
        }

        public function getAuthPassword()
        {
            return '';
        }

        public function getRememberToken()
        {
            return '';
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName()
        {
            return '';
        }
    };

    $this->actingAs($subject);

    expect(fn () => (new ForcePasswordChange)->getUser())->toThrow(LogicException::class);
});

it('rotates the password, stamps, records history and fires the event on the happy path', function (): void {
    Event::fake([PasswordRotated::class]);

    $user = rotatable();
    $this->actingAs($user);
    $historyBefore = PasswordHistory::query()->count();

    livewire(ForcePasswordChange::class)
        ->fillForm([
            'currentPassword' => 'password',
            'password' => 'Sup3r-Str0ng-Pass!',
            'passwordConfirmation' => 'Sup3r-Str0ng-Pass!',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertRedirect(Filament::getUrl())
        ->assertNotified();

    $after = User::query()->findOrFail($user->getKey());

    expect(Hash::check('Sup3r-Str0ng-Pass!', $after->password))->toBeTrue()
        ->and($after->password_changed_at->isToday())->toBeTrue()
        ->and(PasswordHistory::query()->count())->toBe($historyBefore + 1)
        // The session hash is re-synced so AuthenticateSession does not log the
        // user out on their next request after rotating.
        ->and(session('password_hash_'.Filament::getAuthGuard()))->toBe($after->getAuthPassword());

    Event::assertDispatched(PasswordRotated::class);
});

it('rejects a weak password', function (): void {
    $this->actingAs(rotatable());

    livewire(ForcePasswordChange::class)
        ->fillForm([
            'currentPassword' => 'password',
            'password' => 'weak',
            'passwordConfirmation' => 'weak',
        ])
        ->call('save')
        ->assertHasFormErrors(['password']);
});

it('rejects a mismatched confirmation', function (): void {
    $this->actingAs(rotatable());

    livewire(ForcePasswordChange::class)
        ->fillForm([
            'currentPassword' => 'password',
            'password' => 'Sup3r-Str0ng-Pass!',
            'passwordConfirmation' => 'something-else',
        ])
        ->call('save')
        ->assertHasFormErrors(['password']);
});

it('rejects the wrong current password', function (): void {
    $this->actingAs(rotatable());

    livewire(ForcePasswordChange::class)
        ->fillForm([
            'currentPassword' => 'not-the-password',
            'password' => 'Sup3r-Str0ng-Pass!',
            'passwordConfirmation' => 'Sup3r-Str0ng-Pass!',
        ])
        ->call('save')
        ->assertHasFormErrors(['currentPassword']);
});

it('requires the current password field when configured', function (): void {
    $this->actingAs(rotatable());

    // currentPassword omitted entirely: the 'required' rule must fire.
    livewire(ForcePasswordChange::class)
        ->fillForm([
            'password' => 'Sup3r-Str0ng-Pass!',
            'passwordConfirmation' => 'Sup3r-Str0ng-Pass!',
        ])
        ->call('save')
        ->assertHasFormErrors(['currentPassword']);
});

it('rejects a new password equal to the current one', function (): void {
    // History off, so the "different from current" closure is the only guard.
    config([
        'filament-password-rotation.history_count' => 0,
        'filament-password-rotation.require_current_password' => false,
    ]);

    $user = rotatable();
    $user->password = 'Str0ng-Curr3nt-Pass!';
    $user->save();

    $this->actingAs(User::query()->findOrFail($user->getKey()));

    livewire(ForcePasswordChange::class)
        ->fillForm([
            'password' => 'Str0ng-Curr3nt-Pass!',
            'passwordConfirmation' => 'Str0ng-Curr3nt-Pass!',
        ])
        ->call('save')
        ->assertHasFormErrors(['password']);
});

it('rejects reusing a previous password when history is enabled', function (): void {
    config([
        'filament-password-rotation.history_count' => 3,
        'filament-password-rotation.require_current_password' => false,
    ]);

    $user = rotatable(); // history contains 'password'

    // Rotate twice so the previous password is strong (passes strength) and
    // distinct from the current one (passes the different check): only the reuse
    // rule can reject it.
    $rotated = User::query()->findOrFail($user->getKey());
    $rotated->password = 'Str0ng-Prev-P4ss!';
    $rotated->save();

    $rotated = User::query()->findOrFail($user->getKey());
    $rotated->password = 'Str0ng-Curr-P4ss!';
    $rotated->save();

    $this->actingAs(User::query()->findOrFail($user->getKey()));

    livewire(ForcePasswordChange::class)
        ->fillForm([
            'password' => 'Str0ng-Prev-P4ss!',
            'passwordConfirmation' => 'Str0ng-Prev-P4ss!',
        ])
        ->call('save')
        ->assertHasFormErrors(['password']);
});

it('rejects reusing the previous password when history keeps a single entry', function (): void {
    // history_count = 1 pins the reuse-rule gate at exactly the boundary: the
    // rule must still be registered (> 0), so a bumped threshold would let the
    // immediately-previous password through.
    config([
        'filament-password-rotation.history_count' => 1,
        'filament-password-rotation.require_current_password' => false,
    ]);

    $user = rotatable();

    $rotated = User::query()->findOrFail($user->getKey());
    $rotated->password = 'Str0ng-Prev-P4ss!';
    $rotated->save();

    $rotated = User::query()->findOrFail($user->getKey());
    $rotated->password = 'Str0ng-Curr-P4ss!';
    $rotated->save();

    $this->actingAs(User::query()->findOrFail($user->getKey()));

    livewire(ForcePasswordChange::class)
        ->fillForm([
            'password' => 'Str0ng-Prev-P4ss!',
            'passwordConfirmation' => 'Str0ng-Prev-P4ss!',
        ])
        ->call('save')
        ->assertHasFormErrors(['password']);
});

it('rotates without the current password when not required and history is disabled', function (): void {
    config([
        'filament-password-rotation.history_count' => 0,
        'filament-password-rotation.require_current_password' => false,
    ]);

    $user = rotatable(); // created under history_count 0: no history recorded
    $this->actingAs($user);

    livewire(ForcePasswordChange::class)
        ->fillForm([
            'password' => 'Fresh-Str0ng-Pass!',
            'passwordConfirmation' => 'Fresh-Str0ng-Pass!',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertRedirect(Filament::getUrl());

    expect(PasswordHistory::query()->count())->toBe(0);
});
