# Changelog

All notable changes to `bbs-lab/filament-password-rotation` will be documented in this file.

## v2.0.0 - 2026-07-23

**Breaking release.** The generic password-rotation domain now lives in the shared base package [`bbs-lab/laravel-password-rotation`](https://github.com/BBS-Lab/laravel-password-rotation), and this package builds its Filament layer on top. Please read the [upgrade guide](UPGRADE.md).

### ⚠️ Breaking changes

- **Rotation config keys moved** from `filament-password-rotation.*` to `laravel-password-rotation.*` (same nine keys, same env vars) — republish the base config. The Filament-specific keys (`slug`, `expiry_action`) stay in `config/filament-password-rotation.php`.
- The `password_histories` table is now owned and **auto-migrated** by the base package; the user-column migration publish tag is now `laravel-password-rotation-user-migration`.
- Overridden `validation.*` translations now live under the `laravel-password-rotation::` namespace.

### ✨ New: `expiry_action`

- `change` (default) — the in-panel forced change-password form, unchanged.
- `reset` — a single "send reset link" button that emails a Filament password-reset link, signs the user out and shows a toast. Requires the model to implement `CanResetPassword` and the panel to enable `->passwordReset()`.

### 🧰 Under the hood

- Depends on `bbs-lab/laravel-password-rotation: ^1.1`; the duplicated generic classes were removed.
- 100% line coverage, PHPStan level 8, CI across Filament 4 & 5 × Laravel 11/12/13.

## v1.0.0 - 2026-07-22

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