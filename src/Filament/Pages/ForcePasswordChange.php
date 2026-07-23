<?php

declare(strict_types=1);

namespace BBSLab\FilamentPasswordRotation\Filament\Pages;

use BBSLab\LaravelPasswordRotation\Rules\PasswordNotReused;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\Rules\Password;
use LogicException;
use SensitiveParameter;

/**
 * Full-page forced password rotation screen. Extends the standard panel Page
 * (so its route is registered via $panel->pages([...])) but renders with the
 * login-style simple layout so no panel navigation is shown.
 *
 * @property-read Schema $form
 */
class ForcePasswordChange extends Page
{
    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    protected static bool $isDiscovered = false;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament-panels::pages.simple';

    protected static string $layout = 'filament-panels::components.layout.simple';

    protected static ?string $memoizedSlug = null;

    public static function getSlug(?Panel $panel = null): string
    {
        // Memoize on first read: getSlug() is first called when Filament builds
        // the panel routes at boot, so the memo pins the route identity to the
        // slug present then. Reading config live on every call would let a
        // runtime config()->set() diverge the request-time route name from the
        // one actually registered, throwing RouteNotFoundException in the
        // middleware instead of redirecting.
        // ponytail: bare static, fine for one slug per boot; if per-panel/tenant
        // slugs are ever needed, key the memo by panel id instead.
        return static::$memoizedSlug ??= (string) config('filament-password-rotation.slug', 'password/rotate');
    }

    public function hasLogo(): bool
    {
        return true;
    }

    public function getTitle(): string|Htmlable
    {
        return __('filament-password-rotation::messages.title');
    }

    public function getHeading(): string|Htmlable
    {
        return __('filament-password-rotation::messages.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __($this->isResetMode()
            ? 'filament-password-rotation::messages.reset_intro'
            : 'filament-password-rotation::messages.intro');
    }

    public function getUser(): Authenticatable&Model
    {
        $user = Filament::auth()->user();

        if (! $user instanceof Model) {
            throw new LogicException('The authenticated user must be an Eloquent model to rotate its password.');
        }

        return $user;
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        // The reset flow carries no fields: the card is just the explanatory
        // subheading and a single "send reset link" button (see getFormActions).
        if ($this->isResetMode()) {
            return $schema->components([]);
        }

        $components = [];

        if (config('laravel-password-rotation.require_current_password')) {
            $components[] = $this->getCurrentPasswordFormComponent();
        }

        $components[] = $this->getPasswordFormComponent();
        $components[] = $this->getPasswordConfirmationFormComponent();

        return $schema->components($components);
    }

    public function content(Schema $schema): Schema
    {
        $handler = $this->isResetMode() ? 'sendReset' : 'save';

        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler($handler)
                ->footer([
                    Actions::make($this->getFormActions())
                        ->fullWidth(),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $user = $this->getUser();

        // The 'password' state is already hashed by dehydrateStateUsing; the
        // model's 'hashed' cast is idempotent, so saving stamps the rotation
        // column, records history and fires PasswordRotated via the trait hooks.
        $user->setAttribute($user->getAuthPasswordName(), $data['password']);
        $user->save();

        // Keep the session's password-hash marker in sync so Filament's
        // AuthenticateSession does not log the user out on the next request after
        // they rotate (the same marker Filament's own EditProfile page refreshes).
        session()->put('password_hash_'.Filament::getAuthGuard(), $data['password']);

        $this->data['currentPassword'] = null;
        $this->data['password'] = null;
        $this->data['passwordConfirmation'] = null;

        Notification::make()
            ->success()
            ->title(__('filament-password-rotation::messages.updated'))
            ->send();

        $this->redirect(Filament::getUrl(), navigate: false);
    }

    public function sendReset(): void
    {
        // Capture the user before logging out; getUser() reads the guard, which
        // is null once we sign them out below.
        $user = $this->getUser();

        assert($user instanceof CanResetPassword);

        $panel = Filament::getCurrentOrDefaultPanel();

        // The emailed link points at the panel's own reset page, so that page
        // must exist. Fail loudly rather than send a link to a missing route
        // (the default broker would build route('password.reset'), which a
        // Filament app does not define).
        if (! $panel instanceof Panel || ! $panel->hasPasswordReset()) {
            throw new LogicException(
                'The "reset" expiry action requires Filament password reset to be enabled on the panel — add ->passwordReset() to the panel.'
            );
        }

        // Mirror Filament's own RequestPasswordReset: send through the panel's
        // broker with a callback that points the notification at the panel's
        // reset route.
        PasswordBroker::broker($panel->getAuthPasswordBroker())->sendResetLink(
            ['email' => $user->getEmailForPasswordReset()],
            function (CanResetPassword $notifiable, #[SensitiveParameter] string $token) use ($panel): void {
                $notification = app(ResetPasswordNotification::class, ['token' => $token]);
                $notification->url = $panel->getResetPasswordUrl($token, $notifiable);
                $notifiable->notify($notification);
            },
        );

        // The emailed reset link is a guest route, so drop the panel session to
        // make it reachable without an authenticated redirect back here.
        Filament::auth()->logout();
        session()->invalidate();
        session()->regenerateToken();

        // Sent after the token regenerate so the flashed notification lives in
        // the fresh session and survives the redirect onto the login page.
        Notification::make()
            ->success()
            ->title(__('filament-password-rotation::messages.reset_sent'))
            ->send();

        $this->redirect(Filament::getLoginUrl(), navigate: false);
    }

    protected function isResetMode(): bool
    {
        return config('filament-password-rotation.expiry_action') === 'reset';
    }

    protected function getCurrentPasswordFormComponent(): Component
    {
        return TextInput::make('currentPassword')
            ->label(__('filament-password-rotation::messages.current_password'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('current-password')
            ->currentPassword(guard: Filament::getAuthGuard())
            ->required()
            ->dehydrated(false);
    }

    protected function getPasswordFormComponent(): Component
    {
        $user = $this->getUser();

        return TextInput::make('password')
            ->label(__('filament-password-rotation::messages.new_password'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('new-password')
            ->required()
            ->rule(Password::default())
            ->same('passwordConfirmation')
            ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($user): void {
                if (is_string($value) && Hash::check($value, $user->getAuthPassword())) {
                    $fail((string) trans('laravel-password-rotation::validation.different'));
                }
            })
            ->rule(new PasswordNotReused($user), (int) config('laravel-password-rotation.history_count') > 0)
            ->dehydrateStateUsing(fn (#[SensitiveParameter] string $state): string => Hash::make($state));
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return TextInput::make('passwordConfirmation')
            ->label(__('filament-password-rotation::messages.confirm_password'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('new-password')
            ->required()
            ->dehydrated(false);
    }

    /**
     * @return array<Action|ActionGroup>
     */
    protected function getFormActions(): array
    {
        if ($this->isResetMode()) {
            return [
                Action::make('sendReset')
                    ->label(__('filament-password-rotation::messages.reset_submit'))
                    ->submit('sendReset'),
            ];
        }

        return [
            Action::make('save')
                ->label(__('filament-password-rotation::messages.submit'))
                ->submit('save'),
        ];
    }
}
