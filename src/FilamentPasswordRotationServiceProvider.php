<?php

declare(strict_types=1);

namespace BBSLab\FilamentPasswordRotation;

use BBSLab\FilamentPasswordRotation\Console\Commands\PasswordRotationReport;
use BBSLab\FilamentPasswordRotation\Filament\ExpiryCallout;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentPasswordRotationServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-password-rotation')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews()
            ->hasMigration('create_password_histories_table')
            ->runsMigrations()
            ->hasCommand(PasswordRotationReport::class);
    }

    public function packageBooted(): void
    {
        // The rotatable model rarely lives on the default "users" table, and the
        // column name is configurable, so this migration is published (not run)
        // for the user to review and rename before applying.
        $this->publishes([
            __DIR__.'/../database/migrations/add_password_changed_at_to_users_table.php.stub' => database_path(
                'migrations/'.date('Y_m_d_His').'_add_password_changed_at_to_users_table.php'
            ),
        ], 'filament-password-rotation-user-migration');

        // Surface a "password expiring soon" callout at the top of every Filament
        // page. Registered once, globally (Filament pages scope render hooks by
        // page class, not panel id); ExpiryCallout gates it to still-valid users
        // nearing expiry, so it stays inert otherwise.
        FilamentView::registerRenderHook(
            PanelsRenderHook::PAGE_START,
            fn (): ?View => (new ExpiryCallout)->render(),
        );
    }
}
