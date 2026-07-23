# Upgrade Guide

## From v1.x to v2.0.0

v2.0.0 extracts the generic password-rotation domain (the `MustRotatePassword`
contract, the `RotatesPassword` trait, the `PasswordHistory` model, the
`PasswordNotReused` rule, the `PasswordRotated` event and the
`password-rotation:report` command) into a shared base package,
[`bbs-lab/laravel-password-rotation`](https://packagist.org/packages/bbs-lab/laravel-password-rotation),
which this package now depends on. All the Filament-specific behaviour (the
forced-change page, the expiry callout, the panel middleware and plugin) is
unchanged. Composer pulls the base package in automatically; the steps below
cover the config, migration and translation namespaces that moved.

### 1. Rotation config keys moved namespace

The generic rotation settings now live in `config/laravel-password-rotation.php`
(shipped by the base package). This package's own `config/filament-password-rotation.php`
keeps only `slug` and the new `expiry_action` (see below).

Move any values you had customised from `filament-password-rotation.*` to
`laravel-password-rotation.*`. The keys are identical:

`enabled`, `morph_key_type`, `days`, `column`, `force_on_first_login`,
`require_current_password`, `history_count`, `warn_days`, `models`.

Republish the base config if you keep a published copy:

```bash
php artisan vendor:publish --tag=laravel-password-rotation-config
```

The environment variables (`PASSWORD_ROTATION_ENABLED`, `PASSWORD_ROTATION_DAYS`,
`PASSWORD_ROTATION_HISTORY_COUNT`, ...) are unchanged, so a `.env`-driven
configuration needs no edits.

### 2. The `password_histories` table is now owned by the base package

The base package ships and **auto-runs** the `create_password_histories_table`
migration, so you no longer need this package to run it. There is no schema
change and no data migration — the table and its columns are identical.

If you publish the user-column (`password_changed_at`) migration, its tag
changed:

```bash
# before (v1.x)
php artisan vendor:publish --tag=filament-password-rotation-user-migration

# now (v2.0.0)
php artisan vendor:publish --tag=laravel-password-rotation-user-migration
```

### 3. Overridden `validation.*` translations moved namespace

If you had overridden the reuse/different validation messages, they now live
under the base namespace. Move your overrides from
`lang/vendor/filament-password-rotation/{locale}/validation.php` to
`lang/vendor/laravel-password-rotation/{locale}/validation.php` (keys `reused`
and `different`). The Filament UI strings (`messages.*`) stay in this package's
`filament-password-rotation::` namespace.

### 4. New option: `expiry_action`

`config/filament-password-rotation.php` gains an `expiry_action` key
(env `PASSWORD_ROTATION_EXPIRY_ACTION`), default `'change'`:

- `'change'` (default) — **unchanged behaviour**: the forced-change page shows
  the in-panel form where the user picks a new password.
- `'reset'` — the forced-change page instead shows a single button that emails a
  password reset link, signs the user out and sends them to the login page. Use
  this when your reset flow (a custom broker, SSO, ...) must own the actual
  password change.

No action is required to keep the v1.x behaviour.
