<?php

declare(strict_types=1);

namespace BBSLab\FilamentPasswordRotation;

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
        // The generic domain (models, migration, report command, reuse rule) now
        // lives in bbs-lab/laravel-password-rotation, which owns and auto-runs the
        // password_histories migration and ships its own config/translations. This
        // package only adds the Filament-specific config, translations and views.
        $package
            ->name('filament-password-rotation')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews();
    }

    public function packageBooted(): void
    {
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
