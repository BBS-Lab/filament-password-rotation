# Changelog

All notable changes to `bbs-lab/filament-password-rotation` will be documented in this file.

## v1.0.0 — Initial release - 2026-07-20

First stable release of **Filament Password Rotation** — force any authenticatable implementing `MustRotatePassword` to rotate its password every N days. When the password expires, a [Filament](https://filamentphp.com) panel middleware redirects the logged-in user to a native, full-page Filament change-password screen. Light, secure, and enabled with a single `->plugin()` line.

### ✨ Features

- **Interface-driven** — `MustRotatePassword` interface + `RotatesPassword` trait on any authenticatable (not tied to `User`); auto-stamps the timestamp on every password change
- **Forced rotation** — configurable period (`days`); expired users are redirected to a native, full-page Filament change screen
- **One-line install** — `->plugin(FilamentPasswordRotationPlugin::make())` appends the `EnsurePasswordIsNotExpired` middleware to the panel's authenticated stack and registers the forced-change page route; gated by config and de-duplicated
- **Reuse prevention** — polymorphic password history rejects the last N passwords (reusable `PasswordNotReused` rule)
- **First-login enforcement** — a `null` timestamp counts as expired, so admin-provisioned accounts must set their own password
- **Expiry warning** — a Filament callout banner at the top of every panel page while the password is within a configurable window (`warn_days`), injected via the `PAGE_START` render hook
- **Tooling** — `password-rotation:report` Artisan command and a `PasswordRotated` event
- **i18n & config** — English & French translations, publishable; every setting is environment-driven

### ✅ Quality

- **100% line coverage**, mutation tested (**MSI ≥ 80%**), PHPStan level 8, Pint
- Verified in CI on **Filament 4 & 5** (Laravel 11/12/13, PHP 8.3/8.4)

### 📦 Requirements

PHP `^8.2` · Filament `^4.0 || ^5.0` · Laravel `^11.0 || ^12.0 || ^13.0`

### 🚀 Installation

```bash
composer require bbs-lab/filament-password-rotation
```

```php
use BBSLab\FilamentPasswordRotation\Concerns\RotatesPassword;
use BBSLab\FilamentPasswordRotation\Contracts\MustRotatePassword;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements MustRotatePassword
{
    use RotatesPassword;
}
```

```php
use BBSLab\FilamentPasswordRotation\FilamentPasswordRotationPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(FilamentPasswordRotationPlugin::make());
}
```
